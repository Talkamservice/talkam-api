<?php

namespace App\Services\Therapist;

use App\Constants\Finance\Payment\PaymentConstants;
use App\Constants\General\StatusConstants;
use App\Constants\Therapist\TherapistConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\MethodsHelper;
use App\Models\Payment;
use App\Models\TherapySession;
use App\Models\User;
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
        $therapist = TherapistDirectoryService::getById($validated['therapist_id']);

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
                'starts_at' => $validated['starts_at'],
                'duration_minutes' => $therapist->session_duration ?: 50,
                'format' => $validated['format'],
                'status' => TherapistConstants::SESSION_PENDING_PAYMENT,
                'amount' => $therapist->session_rate,
                'currency' => config('therapist.session_rate.currency'),
                'notes' => $validated['notes'] ?? null,
                'hold_expires_at' => now()->addMinutes(config('therapist.booking.hold_minutes')),
            ]);

            DB::commit();
            return $session;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
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
    public function initiatePayment(User $user, $booking_id): array
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
            'metadata' => ['booking_id' => $session->id],
            'status' => StatusConstants::PENDING,
        ]);

        return [
            'reference' => $payment->reference,
            'amount' => $session->amount,
            'currency' => $session->currency,
            'customer' => [
                'email' => $user->email,
                'name' => $user->full_name,
            ],
            'meta' => [
                'activity' => PaymentConstants::PAYMENT_FOR_SESSION,
                'booking_id' => $session->id,
            ],
        ];
    }

    public static function listFor(User $user): array
    {
        $sessions = TherapySession::with(['therapist.user', 'payment'])
            ->where('user_id', $user->id)
            ->orderBy('starts_at')
            ->get();

        [$upcoming, $past] = $sessions->partition(fn ($s) => $s->starts_at->isFuture());

        return [
            'upcoming' => $upcoming->map(fn ($s) => self::detail($s))->values()->all(),
            'past' => $past->map(fn ($s) => self::detail($s))->values()->all(),
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
            'amount' => $session->amount,
            'currency' => $session->currency,
            'notes' => $session->notes,
            'payment_reference' => $session->payment?->reference,
        ];
    }
}
