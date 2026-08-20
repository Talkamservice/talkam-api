<?php

namespace App\Services\Therapist;

use App\Constants\Business\SessionCoverageConstants as Cov;
use App\Constants\Finance\Payment\PaymentConstants;
use App\Constants\General\StatusConstants;
use App\Constants\Therapist\TherapistConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\MethodsHelper;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\TherapySession;
use App\Models\User;
use App\Services\Business\BundleLedgerService;
use App\Services\Business\CoverageResolver;
use App\Services\Business\SessionCapService;
use App\Services\Finance\PaymentGateways\Flutterwave\FlutterwaveService;
use App\Services\System\ExceptionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SessionBookingService
{
    /**
     * Book a slot: status pending_payment with a config-driven hold. The
     * amount is always computed server-side from the therapist's stored rate.
     */
    public function create(User $user, array $data): TherapySession
    {
        $validator = Validator::make($data, [
            'therapist_id' => 'required|exists:therapists,id',
            'starts_at' => 'required|date',
            'format' => ['required', Rule::in(TherapistConstants::SESSION_FORMATS)],
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        $therapist = TherapistDirectoryService::getById($validated['therapist_id'], $user);

        $formats = $therapist->session_formats ?? TherapistConstants::SESSION_FORMATS;
        if (!in_array($validated['format'], $formats)) {
            throw ValidationException::withMessages([
                'format' => ['This therapist does not offer that session format.'],
            ]);
        }

        if (!TherapistSlotService::isBookable($therapist, $validated['starts_at'])) {
            throw ValidationException::withMessages([
                'starts_at' => ['This slot is not available.'],
            ]);
        }

        // Admin-set per-employee cap (web §03 Session Policy) — independent of
        // the coverage flag below; a raw count of the employee's own bookings.
        SessionCapService::enforceBeforeBooking($user);

        // §09 coverage: consumer (unchanged) or one of the org-covered modes.
        // Feature-flagged — with coverage OFF this always resolves to consumer.
        $coverage = CoverageResolver::resolve($user, $therapist);

        if ($coverage['coverage'] === Cov::BLOCKED) {
            throw ValidationException::withMessages([
                'therapist_id' => [$coverage['blocked_reason']],
            ]);
        }

        $org_covered = $coverage['coverage'] !== Cov::CONSUMER;

        // A consumer-pay booking must have a price to charge. A therapist with
        // no session rate hasn't finished pricing setup — e.g. one an employer
        // just brought in (provisioned bare, rate still null) — so there is
        // nothing to charge. Reject cleanly instead of letting a null amount
        // hit the NOT NULL column as a raw 500. (Org-covered pricing is the
        // separate, still-unbuilt B2B path, so it is intentionally not touched
        // here — see planning-docs B2B fulfilment.)
        if (!$org_covered && !((float) $therapist->session_rate > 0)) {
            throw ValidationException::withMessages([
                'therapist_id' => ['This therapist is not open for booking yet.'],
            ]);
        }

        DB::beginTransaction();
        try {
            // Active-status double-booking guard (service-level; a partial
            // unique index is not portable across drivers).
            $clash = TherapySession::where('therapist_id', $therapist->id)
                ->where('starts_at', $validated['starts_at'])
                ->active()
                ->lockForUpdate()
                ->exists();

            if ($clash) {
                throw ValidationException::withMessages([
                    'starts_at' => ['This slot has just been taken.'],
                ]);
            }

            $session = TherapySession::create([
                'uuid' => strtoupper(MethodsHelper::getRandomToken(10)),
                'user_id' => $user->id,
                'therapist_id' => $therapist->id,
                'organization_id' => $coverage['organization_id'],
                'coverage' => $coverage['coverage'],
                'billed_amount' => $coverage['billed_amount'],
                'starts_at' => $validated['starts_at'],
                'duration_minutes' => $therapist->session_duration ?: 50,
                'format' => $validated['format'],
                // Every new session — org-covered or consumer — starts out
                // needing the therapist's own review before it's real: an
                // org-covered one has nothing to pay, but the therapist still
                // gets to accept or decline it (acknowledge() confirms it),
                // same as the request/propose flow. See "Requests" tab.
                'status' => TherapistConstants::SESSION_PENDING_PAYMENT,
                // An org-brought-in therapist often has no consumer session_rate
                // set (never needed one — org_external is settled outside
                // TalkAM entirely, per EarningsLedgerService::creditForSession).
                // Fall back to what the org is actually billed rather than hit
                // the NOT NULL column with a real gap the guard above only
                // covers for the consumer path.
                'amount' => $therapist->session_rate ?? $coverage['billed_amount'] ?? 0,
                'currency' => config('therapist.session_rate.currency'),
                'notes' => $validated['notes'] ?? null,
                'hold_expires_at' => $org_covered
                    ? null
                    : now()->addMinutes(config('therapist.booking.hold_minutes')),
            ]);

            // Prepay: reserve a bundle session now; refunded if it is cancelled.
            if ($coverage['coverage'] === Cov::ORG_BUNDLE) {
                BundleLedgerService::draw(Organization::find($coverage['organization_id']), $session);
            }

            DB::commit();
            return $session;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    /**
     * The therapist's review just cleared a pending session — for an
     * org-covered one that IS the accept (there's no payment to wait on), so
     * it goes straight to confirmed. A consumer session stays pending_payment
     * regardless; the client still has to pay. Shared by SessionRequestController::
     * acknowledge() and TherapistSessionRequestService::propose() (proposing
     * a concrete time is itself the therapist's acceptance).
     */
    public static function confirmIfAwaitingReview(TherapySession $session): TherapySession
    {
        if ($session->status === TherapistConstants::SESSION_PENDING_PAYMENT
            && $session->coverage !== Cov::CONSUMER) {
            $session->update(['status' => TherapistConstants::SESSION_CONFIRMED]);
        }

        return $session->refresh();
    }

    public static function getOwnedByUser($booking_id, User $user): TherapySession
    {
        $session = TherapySession::where('id', $booking_id)
            ->where('user_id', $user->id)
            ->first();

        if (empty($session)) {
            throw new ModelNotFoundException("Booking not found");
        }

        return $session;
    }

    /**
     * Payment initiation (subscriptions-initiate pattern): a pending
     * payments row + the checkout payload. Also serves Retry for failed
     * bookings.
     */
    public function initiatePayment(User $user, $booking_id, array $data = []): array
    {
        $session = self::getOwnedByUser($booking_id, $user);
        if ($session->status == TherapistConstants::SESSION_CONFIRMED) {
            throw new InvalidRequestException("This session has already been paid for.");
        }

        if (!in_array($session->status, [
            TherapistConstants::SESSION_PENDING_PAYMENT,
            TherapistConstants::SESSION_FAILED,
        ])) {
            throw new InvalidRequestException("This booking cannot be paid for.");
        }

        // Retrying a failed booking re-holds the slot.
        if ($session->status == TherapistConstants::SESSION_FAILED) {
            $session->update([
                'status' => TherapistConstants::SESSION_PENDING_PAYMENT,
                'hold_expires_at' => now()->addMinutes(config('therapist.booking.hold_minutes')),
            ]);
        }

        $reference = "TK-SESS-" . strtoupper(MethodsHelper::getRandomToken(10));

        $payment = Payment::create([
            'user_id' => $user->id,
            'currency' => $session->currency,
            'amount' => $session->amount,
            'reference' => $reference,
            'activity' => PaymentConstants::PAYMENT_FOR_SESSION,
            'description' => "Therapy session payment",
            'type' => PaymentConstants::DEBIT,
            'metadata' => [
                'booking_id' => $session->id,
                // Opt-in tokenization (§09): the callback stores the card.
                'save_card' => (bool) ($data['save_card'] ?? false),
            ],
            'status' => StatusConstants::PENDING,
        ]);

        // Saved-card checkout (§09): PIN-authorized tokenized charge.
        if (!empty($data['payment_method_id'])) {
            return $this->chargeSavedCard($user, $session, $payment, $data);
        }

        $customer = [
            'email' => $user->email,
            'name' => $user->full_name,
        ];
        $meta = [
            'activity' => PaymentConstants::PAYMENT_FOR_SESSION,
            'booking_id' => $session->id,
        ];

        return [
            'reference' => $payment->reference,
            'amount' => $session->amount,
            'currency' => $session->currency,
            // Hosted checkout (mobile: open in a webview instead of embedding
            // the inline SDK; web keeps using reference/amount/customer above
            // for its own inline widget, so this failing never blocks that).
            'link' => $this->checkoutLink($payment, $session, $customer, $meta),
            'customer' => $customer,
            'meta' => $meta,
        ];
    }

    private function checkoutLink(Payment $payment, TherapySession $session, array $customer, array $meta): ?string
    {
        try {
            $checkout = app(FlutterwaveService::class)->createCheckoutLink([
                'tx_ref' => $payment->reference,
                'amount' => $session->amount,
                'currency' => $session->currency,
                'redirect_url' => config('services.flutterwave.redirectUrl'),
                'customer' => $customer,
                'customizations' => ['title' => 'TalkAM session'],
                'meta' => $meta,
            ]);

            return $checkout['link'] ?? null;
        } catch (\Throwable $e) {
            ExceptionService::logAndBroadcast($e);
            return null;
        }
    }

    /**
     * §09: charge a saved card. The payment PIN is the authorization
     * factor — a wrong PIN never reaches the provider.
     */
    private function chargeSavedCard(User $user, TherapySession $session, Payment $payment, array $data): array
    {
        $method = \App\Services\User\PaymentMethodService::getOwned($user, $data['payment_method_id']);

        if (!\App\Services\User\PaymentPinService::verifyPin($user, $data['payment_pin'] ?? null)) {
            throw new InvalidRequestException("Invalid payment PIN.");
        }

        $charge = app(\App\Services\Finance\PaymentGateways\Flutterwave\FlutterwaveService::class)
            ->chargeWithToken($method->token, [
                'tx_ref' => $payment->reference,
                'amount' => $session->amount,
                'currency' => $session->currency,
                'email' => $user->email,
            ]);

        if (($charge['status'] ?? null) == 'successful') {
            $payment->update(['status' => StatusConstants::COMPLETED]);
            $session->update([
                'status' => TherapistConstants::SESSION_CONFIRMED,
                'payment_id' => $payment->id,
                'hold_expires_at' => null,
            ]);
        }

        return [
            'reference' => $payment->reference,
            'amount' => $session->amount,
            'currency' => $session->currency,
            'charged' => ($charge['status'] ?? null) == 'successful',
            'status' => $session->refresh()->status,
        ];
    }

    public static function listFor(User $user): array
    {
        $sessions = TherapySession::with(['therapist.user', 'payment', 'review'])
            ->where('user_id', $user->id)
            ->orderBy('starts_at')
            ->get();

        // A future-dated row that's cancelled/failed/expired/no-show is a dead
        // record, not something still coming up — the "next session" widget
        // takes upcoming[0] on faith, so a stale cancelled session sorting in
        // there would otherwise pose as the live one (Reschedule/Cancel/Join
        // all wired to it) until someone actually acts on it and hits a stale
        // "can no longer be cancelled" style rejection.
        [$upcoming, $past] = $sessions->partition(
            fn ($s) => $s->starts_at->isFuture() && in_array($s->status, [
                TherapistConstants::SESSION_PENDING_PAYMENT,
                TherapistConstants::SESSION_CONFIRMED,
                TherapistConstants::SESSION_IN_PROGRESS,
            ])
        );

        return [
            'upcoming' => $upcoming->map(fn ($s) => self::detail($s))->values()->all(),
            'past' => $past->map(fn ($s) => self::detail($s))->values()->all(),
        ];
    }

    /**
     * Therapist my-sessions (§12): mirrored list with per-session NET
     * earnings (share config) and the client's rating.
     */
    public static function listForTherapist(\App\Models\Therapist $therapist): array
    {
        $share = (float) config('therapist.platform_share_percent');

        $sessions = TherapySession::with(['user', 'payment', 'review', 'note'])
            ->where('therapist_id', $therapist->id)
            ->orderBy('starts_at')
            ->get();

        // See listFor() — a future-dated cancelled/expired row is a dead
        // record, not something still coming up.
        [$upcoming, $past] = $sessions->partition(
            fn ($s) => $s->starts_at->isFuture() && in_array($s->status, [
                TherapistConstants::SESSION_PENDING_PAYMENT,
                TherapistConstants::SESSION_CONFIRMED,
                TherapistConstants::SESSION_IN_PROGRESS,
            ])
        );

        $serialize = fn ($s) => array_merge(self::detail($s), [
            'client_id' => $s->user_id,
            'client_name' => $s->user?->full_name,
            'earnings' => round((float) $s->amount * (1 - $share / 100), 2),
        ]);

        return [
            'upcoming' => $upcoming->map($serialize)->values()->all(),
            'past' => $past->map($serialize)->values()->all(),
        ];
    }

    public static function detail(TherapySession $session): array
    {
        return [
            'id' => $session->id,
            'uuid' => $session->uuid,
            'therapist_id' => $session->therapist_id,
            'therapist_name' => $session->therapist?->user?->full_name,
            'starts_at' => $session->starts_at->toDateTimeString(),
            'duration_minutes' => $session->duration_minutes,
            'format' => $session->format,
            'status' => $session->status,
            // Who's actually paying (§09) — the Requests tab uses this to
            // tell an org-covered "acknowledging IS confirming" request apart
            // from a consumer one that still needs the client to pay.
            'coverage' => $session->coverage,
            // The Requests tab's own triage state — acknowledging is the
            // therapist's side of "dealt with"; a consumer session stays
            // pending until the client pays, an org-covered one confirms
            // right on acknowledge (§10, §3a).
            'acknowledged_at' => $session->acknowledged_at?->toDateTimeString(),
            'amount' => $session->amount,
            'currency' => $session->currency,
            'notes' => $session->notes,
            'has_note' => $session->note?->status === 'final',
            'payment_reference' => $session->payment?->reference,
            'rating' => $session->review?->rating,
            // Client-owned pre/post mood (web §02) — the deck's "😔 → 🙂" pips.
            'client_pre_mood' => $session->client_pre_mood,
            'client_post_mood' => $session->client_post_mood,
            'receipt_url' => !empty($session->payment_id)
                ? url("/api/v2/user/bookings/{$session->id}/receipt")
                : null,
            // Only notes the therapist explicitly shared (§11 privacy).
            'shared_note' => SessionNoteService::sharedNoteFor($session),
            // A pending reschedule proposal on this session, if any — the
            // requester waits, the counterpart gets an accept/decline prompt.
            // Both dashboards need this to actually surface the respond step;
            // there is otherwise no way to discover a reschedule's id at all.
            'pending_reschedule' => self::pendingReschedule($session),
        ];
    }

    public static function pendingReschedule(TherapySession $session): ?array
    {
        $reschedule = \App\Models\SessionReschedule::where('session_id', $session->id)
            ->where('status', \App\Constants\Therapist\SessionConstants::RESCHEDULE_PENDING)
            ->latest()
            ->first();

        if (empty($reschedule)) {
            return null;
        }

        return [
            'id' => $reschedule->id,
            'new_starts_at' => $reschedule->new_starts_at->toDateTimeString(),
            'reason' => $reschedule->reason,
            'requested_by' => $reschedule->requested_by,
        ];
    }
}
