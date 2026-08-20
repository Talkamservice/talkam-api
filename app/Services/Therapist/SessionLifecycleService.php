<?php

namespace App\Services\Therapist;

use App\Constants\Business\SessionCoverageConstants as Cov;
use App\Constants\Therapist\SessionConstants;
use App\Constants\Therapist\TherapistConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\Organization;
use App\Models\TherapySession;
use App\Models\User;
use App\Services\Business\BundleLedgerService;
use App\Notifications\Therapist\SessionCancelledNotification;
use App\Services\Finance\PaymentGateways\Flutterwave\FlutterwaveService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Session state machine: cancel (policy-gated refunds), join (AV token,
 * in_progress stamping), auto-completion / no-show sweep.
 */
class SessionLifecycleService
{
    public $call_service;

    public function __construct()
    {
        $this->call_service = new SessionCallService;
    }

    /**
     * A booking visible to either participant (client or therapist).
     */
    public static function getForParticipant($booking_id, User $user): TherapySession
    {
        $session = TherapySession::with('therapist')->find($booking_id);

        if (empty($session)) {
            throw new ModelNotFoundException("Booking not found");
        }

        $is_client = $session->user_id == $user->id;
        $is_therapist = $session->therapist?->user_id == $user->id;

        if (!$is_client && !$is_therapist) {
            throw new ModelNotFoundException("Booking not found");
        }

        return $session;
    }

    /** Why a session in this status specifically can't be cancelled — shown
     *  to the user instead of a blanket "can no longer be cancelled". */
    private const UNCANCELLABLE_STATUS_REASONS = [
        TherapistConstants::SESSION_IN_PROGRESS => "This session is already in progress.",
        TherapistConstants::SESSION_COMPLETED => "This session has already ended.",
        TherapistConstants::SESSION_CANCELLED => "This session has already been cancelled.",
        TherapistConstants::SESSION_FAILED => "This session couldn't be set up, so there's nothing to cancel.",
        TherapistConstants::SESSION_EXPIRED => "This session's payment window already expired.",
        TherapistConstants::SESSION_NO_SHOW => "This session was already marked as a no-show.",
    ];

    public function cancel(User $user, $booking_id, array $data): TherapySession
    {
        $validator = Validator::make($data, [
            'reason' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $session = self::getForParticipant($booking_id, $user);

        if (!in_array($session->status, [
            TherapistConstants::SESSION_CONFIRMED,
            TherapistConstants::SESSION_PENDING_PAYMENT,
        ])) {
            throw new InvalidRequestException(
                self::UNCANCELLABLE_STATUS_REASONS[$session->status] ?? "This session can no longer be cancelled."
            );
        }

        $is_therapist = $session->therapist?->user_id == $user->id;
        $free_window_hours = config('therapist.sessions.free_cancellation_hours');
        $outside_window = now()->diffInHours($session->starts_at, false) >= $free_window_hours;

        // Refund policy: therapist-initiated always refunds; client refunds
        // only outside the free-cancellation window. Client no-show never
        // reaches here (sweep handles it).
        $refund_due = !empty($session->payment_id)
            && ($is_therapist || $outside_window);

        if ($refund_due) {
            $payment = $session->payment;
            app(FlutterwaveService::class)->refundTransaction($payment->reference, [
                'amount' => $payment->amount,
            ]);
        }

        $session->update([
            'status' => TherapistConstants::SESSION_CANCELLED,
            'cancelled_by' => $is_therapist
                ? SessionConstants::CANCELLED_BY_THERAPIST
                : SessionConstants::CANCELLED_BY_CLIENT,
            'cancellation_reason' => $validator->validated()['reason'] ?? null,
            'hold_expires_at' => null,
        ]);

        // §09: return the drawn prepaid-bundle session to the company (inert for consumer).
        $this->refundBundleIfCovered($session);

        $counterpart = $is_therapist ? $session->user : $session->therapist?->user;
        if (!empty($counterpart)) {
            Notification::send($counterpart, new SessionCancelledNotification($session, $refund_due));
        }

        return $session->refresh();
    }

    /** Why a session in this status specifically can't be joined — shown to
     *  the user instead of a blanket "cannot be joined". */
    private const UNJOINABLE_STATUS_REASONS = [
        TherapistConstants::SESSION_COMPLETED => "This session has already ended.",
        TherapistConstants::SESSION_CANCELLED => "This session was cancelled.",
        TherapistConstants::SESSION_FAILED => "This session couldn't be set up — please book a new one.",
        TherapistConstants::SESSION_EXPIRED => "This session's payment window expired before it was confirmed.",
        TherapistConstants::SESSION_NO_SHOW => "This session was marked as a no-show.",
    ];

    public function join(User $user, $booking_id): array
    {
        $session = self::getForParticipant($booking_id, $user);

        if (!in_array($session->status, [
            TherapistConstants::SESSION_CONFIRMED,
            TherapistConstants::SESSION_IN_PROGRESS,
        ])) {
            // pending_payment reads differently depending on who's paying for
            // it (§09) — everything else has one fixed reason.
            $reason = $session->status === TherapistConstants::SESSION_PENDING_PAYMENT
                ? ($session->coverage === Cov::CONSUMER
                    ? "Payment for this session hasn't been completed yet."
                    : "Your therapist hasn't accepted this session yet.")
                : (self::UNJOINABLE_STATUS_REASONS[$session->status] ?? "This session cannot be joined.");

            throw new InvalidRequestException($reason);
        }

        $window_opens = $session->starts_at->copy()
            ->subMinutes(config('therapist.sessions.join_early_minutes'));

        if (now()->lt($window_opens)) {
            $starts_at = $session->starts_at;
            $when = $starts_at->isToday()
                ? ''
                : ($starts_at->isTomorrow()
                    ? ' tomorrow'
                    : ' in ' . now()->startOfDay()->diffInDays($starts_at->copy()->startOfDay()) . ' days');

            throw new InvalidRequestException(
                "This session starts{$when} at {$starts_at->format('g:ia')} — "
                . "you can join from {$window_opens->format('g:ia')}."
            );
        }

        $is_therapist = $session->therapist?->user_id == $user->id;

        // Mint the channel/token BEFORE touching the session row — if the AV
        // provider isn't configured (or the request otherwise fails), the
        // session must not be left stamped in_progress/joined with no actual
        // way to join, which would silently vanish it from "upcoming" once
        // its start time passes without ever having had a working call.
        $channel_ref = $this->call_service->channelFor($session);
        $token = $this->call_service->token($session, $user->id);

        $updates = [];
        if (empty($session->started_at)) {
            $updates['started_at'] = now();
            $updates['status'] = TherapistConstants::SESSION_IN_PROGRESS;
        }
        if ($is_therapist && empty($session->therapist_joined_at)) {
            $updates['therapist_joined_at'] = now();
        }
        if (!$is_therapist && empty($session->client_joined_at)) {
            $updates['client_joined_at'] = now();
        }
        if (!empty($updates)) {
            $session->update($updates);
        }

        return [
            'channel_ref' => $channel_ref,
            'token' => $token,
            'starts_at' => $session->starts_at->toDateTimeString(),
            'duration_minutes' => $session->duration_minutes,
        ];
    }

    /**
     * Opens (or reuses) the messaging thread tied to this booking. Resolving
     * the counterpart from the session server-side — rather than trusting a
     * client-supplied receiver_id — is what lets the therapist-facing app
     * never learn a client's real user id, only their anonymised client_ref.
     * A confirmed/in-progress/completed booking is proof enough these two
     * are allowed to talk, so it skips the AWAITING_RESPONSE gate a
     * cold-start conversation would normally sit in.
     */
    public function startConversation(User $user, $booking_id): array
    {
        $session = self::getForParticipant($booking_id, $user);

        $is_client = $session->user_id == $user->id;
        $counterpart_id = $is_client ? $session->therapist?->user_id : $session->user_id;

        if (empty($counterpart_id)) {
            throw new InvalidRequestException("There's no one to message on this session yet.");
        }

        $conversation = (new \App\Services\Messaging\ConversationService)->create([
            'receiver_id' => $counterpart_id,
        ]);

        if ($conversation->status === \App\Constants\General\StatusConstants::AWAITING_RESPONSE) {
            $conversation->update(['status' => \App\Constants\General\StatusConstants::ACTIVE]);
        }

        return \App\Services\Messaging\V2\ConversationStateService::serialize($conversation->refresh(), $user);
    }

    /**
     * AV webhook: room closed → ended_at + auto-complete.
     */
    public function handleRoomClosed(?string $channel_ref): ?TherapySession
    {
        if (empty($channel_ref)) {
            return null;
        }

        $session = TherapySession::where('channel_ref', $channel_ref)->first();
        if (empty($session) || $session->status != TherapistConstants::SESSION_IN_PROGRESS) {
            return null;
        }

        $session->update([
            'ended_at' => now(),
            'status' => TherapistConstants::SESSION_COMPLETED,
        ]);

        EarningsLedgerService::creditForSession($session->refresh());

        return $session;
    }

    /**
     * Straggler sweep: sessions past their end time become completed,
     * no_show (client absent, no refund) or no_show + refund (therapist
     * absent while the client showed up).
     */
    public function sweep(): void
    {
        $sessions = TherapySession::with('payment')
            ->whereIn('status', [
                TherapistConstants::SESSION_CONFIRMED,
                TherapistConstants::SESSION_IN_PROGRESS,
            ])
            ->get()
            ->filter(fn ($s) => $s->starts_at->copy()->addMinutes($s->duration_minutes)->isPast());

        foreach ($sessions as $session) {
            if (empty($session->client_joined_at)) {
                // Client no-show: no money refund, but the company's bundle is returned.
                $session->update([
                    'status' => TherapistConstants::SESSION_NO_SHOW,
                    'ended_at' => now(),
                ]);
                $this->refundBundleIfCovered($session);
                continue;
            }

            if (empty($session->therapist_joined_at)) {
                // Therapist no-show: full refund.
                if (!empty($session->payment_id)) {
                    app(FlutterwaveService::class)->refundTransaction(
                        $session->payment->reference,
                        ['amount' => $session->payment->amount]
                    );
                }
                $session->update([
                    'status' => TherapistConstants::SESSION_NO_SHOW,
                    'ended_at' => now(),
                ]);
                $this->refundBundleIfCovered($session);
                continue;
            }

            $session->update([
                'status' => TherapistConstants::SESSION_COMPLETED,
                'ended_at' => $session->ended_at ?? now(),
            ]);

            EarningsLedgerService::creditForSession($session->refresh());
        }
    }

    /**
     * Return a drawn prepaid-bundle session to the company's balance when a
     * covered session is cancelled or no-shows (web §09). Idempotent, and inert
     * for consumer sessions (coverage defaults to consumer, so this is a no-op).
     */
    private function refundBundleIfCovered(TherapySession $session): void
    {
        if ($session->coverage === Cov::ORG_BUNDLE && $session->organization_id) {
            BundleLedgerService::refund(Organization::find($session->organization_id), $session);
        }
    }

    public static function receipt($booking_id, User $user): array
    {
        $session = SessionBookingService::getOwnedByUser($booking_id, $user);

        if (empty($session->payment_id)) {
            throw new InvalidRequestException("No payment exists for this booking.");
        }

        $payment = $session->payment;

        return [
            'reference' => $payment->reference,
            'amount' => $payment->amount,
            'fees' => $payment->fees,
            'currency' => $session->currency,
            'narration' => $payment->narration,
            'paid_at' => formatDate($payment->updated_at),
            'session' => [
                'starts_at' => $session->starts_at->toDateTimeString(),
                'duration_minutes' => $session->duration_minutes,
                'format' => $session->format,
                'therapist_name' => $session->therapist?->user?->full_name,
            ],
        ];
    }
}
