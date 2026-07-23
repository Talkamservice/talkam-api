<?php

namespace App\Services\Therapist;

use App\Constants\Therapist\SessionConstants;
use App\Constants\Therapist\TherapistConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\TherapySession;
use App\Models\User;
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
            throw new InvalidRequestException("This session can no longer be cancelled.");
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

        $counterpart = $is_therapist ? $session->user : $session->therapist?->user;
        if (!empty($counterpart)) {
            Notification::send($counterpart, new SessionCancelledNotification($session, $refund_due));
        }

        return $session->refresh();
    }

    public function join(User $user, $booking_id): array
    {
        $session = self::getForParticipant($booking_id, $user);

        if (!in_array($session->status, [
            TherapistConstants::SESSION_CONFIRMED,
            TherapistConstants::SESSION_IN_PROGRESS,
        ])) {
            throw new InvalidRequestException("This session cannot be joined.");
        }

        $window_opens = $session->starts_at->copy()
            ->subMinutes(config('therapist.sessions.join_early_minutes'));

        if (now()->lt($window_opens)) {
            throw new InvalidRequestException("The session has not started yet.");
        }

        $is_therapist = $session->therapist?->user_id == $user->id;

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
            'channel_ref' => $this->call_service->channelFor($session),
            'token' => $this->call_service->token($session, $user->id),
            'starts_at' => $session->starts_at->toDateTimeString(),
            'duration_minutes' => $session->duration_minutes,
        ];
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
                // Client no-show: no refund.
                $session->update([
                    'status' => TherapistConstants::SESSION_NO_SHOW,
                    'ended_at' => now(),
                ]);
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
                continue;
            }

            $session->update([
                'status' => TherapistConstants::SESSION_COMPLETED,
                'ended_at' => $session->ended_at ?? now(),
            ]);

            EarningsLedgerService::creditForSession($session->refresh());
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
