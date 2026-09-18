<?php

namespace App\Services\Therapist;

use App\Constants\Therapist\SessionConstants;
use App\Constants\Therapist\TherapistConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\SessionReschedule;
use App\Models\User;
use App\Notifications\Therapist\SessionRescheduleNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SessionRescheduleService
{
    /**
     * Reschedule is a request the counterpart accepts or declines.
     * Config-driven limits: max requests + cutoff before start.
     */
    public function request(User $user, $booking_id, array $data): SessionReschedule
    {
        $validator = Validator::make($data, [
            'new_starts_at' => 'required|date|after:now',
            'reason' => ['required', Rule::in(SessionConstants::RESCHEDULE_REASONS)],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        $session = SessionLifecycleService::getForParticipant($booking_id, $user);

        if ($session->status != TherapistConstants::SESSION_CONFIRMED) {
            throw new InvalidRequestException("Only confirmed sessions can be rescheduled.");
        }

        $cutoff_hours = config('therapist.sessions.reschedule_cutoff_hours');
        if (now()->diffInHours($session->starts_at, false) < $cutoff_hours) {
            throw new InvalidRequestException(
                "Sessions can no longer be rescheduled within $cutoff_hours hours of the start time."
            );
        }

        $max = config('therapist.sessions.reschedule_max_requests');
        $prior = SessionReschedule::where('session_id', $session->id)
            ->whereIn('status', [SessionConstants::RESCHEDULE_PENDING, SessionConstants::RESCHEDULE_DECLINED])
            ->count();

        if ($prior >= $max) {
            throw new InvalidRequestException("This session has reached the maximum of $max reschedule requests.");
        }

        if (!TherapistSlotService::isBookable($session->therapist, $validated['new_starts_at'])) {
            throw ValidationException::withMessages([
                'new_starts_at' => ['The proposed slot is not available.'],
            ]);
        }

        $reschedule = SessionReschedule::create([
            'session_id' => $session->id,
            'requested_by' => $user->id,
            'old_starts_at' => $session->starts_at,
            'new_starts_at' => $validated['new_starts_at'],
            'reason' => $validated['reason'],
            'status' => SessionConstants::RESCHEDULE_PENDING,
        ]);

        $is_therapist = $session->therapist?->user_id == $user->id;
        $counterpart = $is_therapist ? $session->user : $session->therapist?->user;
        if (!empty($counterpart)) {
            Notification::send($counterpart, new SessionRescheduleNotification($reschedule, 'requested'));
        }

        return $reschedule;
    }

    /**
     * Counterpart response. Accept = same-price slot move; never a payment
     * delta.
     */
    public function respond(User $user, $reschedule_id, array $data): SessionReschedule
    {
        $validator = Validator::make($data, [
            'action' => ['required', Rule::in(['accept', 'decline'])],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $reschedule = SessionReschedule::with('session.therapist')->find($reschedule_id);
        if (empty($reschedule)) {
            throw new ModelNotFoundException("Reschedule request not found");
        }

        $session = $reschedule->session;
        $is_participant = $session->user_id == $user->id
            || $session->therapist?->user_id == $user->id;

        if (!$is_participant) {
            throw new ModelNotFoundException("Reschedule request not found");
        }

        if ($reschedule->requested_by == $user->id) {
            throw new InvalidRequestException("You cannot respond to your own reschedule request.");
        }

        if ($reschedule->status != SessionConstants::RESCHEDULE_PENDING) {
            throw new InvalidRequestException("This request has already been responded to.");
        }

        DB::beginTransaction();
        try {
            if ($data['action'] == 'accept') {
                $session->update(['starts_at' => $reschedule->new_starts_at]);
                $reschedule->update([
                    'status' => SessionConstants::RESCHEDULE_ACCEPTED,
                    'responded_at' => now(),
                ]);
            } else {
                $reschedule->update([
                    'status' => SessionConstants::RESCHEDULE_DECLINED,
                    'responded_at' => now(),
                ]);
            }
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }

        Notification::send(
            $reschedule->requester,
            new SessionRescheduleNotification($reschedule->refresh(), $reschedule->status)
        );

        return $reschedule;
    }
}
