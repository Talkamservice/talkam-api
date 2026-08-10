<?php

namespace App\Services\Therapist;

use App\Constants\Therapist\SessionConstants;
use App\Constants\Therapist\TherapistConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\Therapist;
use App\Models\TherapistSessionRequest;
use App\Models\UserInterest;
use App\Models\User;
use App\Notifications\Therapist\TherapistSessionRequestNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Inbound "I'd like to book" requests that don't carry a committed slot —
 * the client names a preferred day/time, the therapist reviews and proposes
 * a real slot from their own availability, which turns the request into an
 * actual (pending_payment) TherapySession. Mirrors SessionReschedule's
 * request/respond shape, but starts before any TherapySession exists.
 */
class TherapistSessionRequestService
{
    public function submit(User $user, array $data): TherapistSessionRequest
    {
        $validator = Validator::make($data, [
            'therapist_id' => 'required|exists:therapists,id',
            'format' => ['required', Rule::in(TherapistConstants::SESSION_FORMATS)],
            'preferred_at' => 'required|date|after:now',
            'note' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        $therapist = Therapist::with('user')->find($validated['therapist_id']);

        $request = TherapistSessionRequest::create([
            'user_id' => $user->id,
            'therapist_id' => $therapist->id,
            'format' => $validated['format'],
            'preferred_at' => $validated['preferred_at'],
            'note' => $validated['note'] ?? null,
            'status' => SessionConstants::REQUEST_PENDING,
        ]);

        if (!empty($therapist->user)) {
            Notification::send($therapist->user, new TherapistSessionRequestNotification($request, 'submitted'));
        }

        return $request;
    }

    /**
     * Pending requests for a therapist's own "Requests" queue, with the
     * client's first interest topic as a display tag (§ dashboard "focus").
     */
    public static function pendingForTherapist(Therapist $therapist): array
    {
        return TherapistSessionRequest::with('user')
            ->where('therapist_id', $therapist->id)
            ->where('status', SessionConstants::REQUEST_PENDING)
            ->latest()
            ->get()
            ->map(fn (TherapistSessionRequest $r) => [
                'id' => $r->id,
                'format' => $r->format,
                'preferred_at' => $r->preferred_at->toDateTimeString(),
                'note' => $r->note,
                'focus' => UserInterest::with('category')->where('user_id', $r->user_id)->first()?->category?->name,
                'created_at' => $r->created_at->toDateTimeString(),
            ])
            ->values()
            ->all();
    }

    private function forTherapist($request_id, User $therapist_user): TherapistSessionRequest
    {
        $request = TherapistSessionRequest::with('therapist.user', 'user')->find($request_id);

        if (empty($request) || $request->therapist?->user_id != $therapist_user->id) {
            throw new ModelNotFoundException("Request not found");
        }

        return $request;
    }

    /**
     * Therapist accepts + proposes a concrete, real slot. Creates the actual
     * TherapySession (through the normal booking path, so coverage/payment
     * rules never diverge) and links it back to the request.
     */
    public function propose(User $therapist_user, $request_id, array $data): TherapistSessionRequest
    {
        $validator = Validator::make($data, [
            'starts_at' => 'required|date|after:now',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $request = $this->forTherapist($request_id, $therapist_user);

        if ($request->status != SessionConstants::REQUEST_PENDING) {
            throw new InvalidRequestException("This request has already been dealt with.");
        }

        $starts_at = $validator->validated()['starts_at'];

        if (!TherapistSlotService::isBookable($request->therapist, $starts_at)) {
            throw ValidationException::withMessages([
                'starts_at' => ['That slot is not available.'],
            ]);
        }

        $session = (new SessionBookingService)->create($request->user, [
            'therapist_id' => $request->therapist_id,
            'starts_at' => $starts_at,
            'format' => $request->format,
        ]);

        // Proposing a concrete time IS the therapist's acceptance — an
        // org-covered session has no payment to wait on, so it confirms
        // right away rather than sitting in a redundant second review step.
        $session = SessionBookingService::confirmIfAwaitingReview($session);

        $request->update([
            'status' => SessionConstants::REQUEST_PROPOSED,
            'session_id' => $session->id,
            'proposed_starts_at' => $starts_at,
            'responded_at' => now(),
        ]);

        Notification::send($request->user, new TherapistSessionRequestNotification($request->refresh(), 'proposed'));

        return $request;
    }

    public function declineByTherapist(User $therapist_user, $request_id): TherapistSessionRequest
    {
        $request = $this->forTherapist($request_id, $therapist_user);

        if ($request->status != SessionConstants::REQUEST_PENDING) {
            throw new InvalidRequestException("This request has already been dealt with.");
        }

        $request->update([
            'status' => SessionConstants::REQUEST_DECLINED,
            'responded_at' => now(),
        ]);

        Notification::send($request->user, new TherapistSessionRequestNotification($request, 'declined_by_therapist'));

        return $request;
    }

    /**
     * The client's own requests — pending ones awaiting review, and proposed
     * ones awaiting their decline (or, for the rare non-covered booking,
     * confirm-and-pay — the session's own status tells the frontend which).
     */
    public static function forClient(User $user): array
    {
        return TherapistSessionRequest::with('therapist.user', 'session')
            ->where('user_id', $user->id)
            ->whereIn('status', [SessionConstants::REQUEST_PENDING, SessionConstants::REQUEST_PROPOSED])
            ->latest()
            ->get()
            ->map(fn (TherapistSessionRequest $r) => [
                'id' => $r->id,
                'status' => $r->status,
                'therapist_name' => $r->therapist?->user?->full_name,
                'format' => $r->format,
                'preferred_at' => $r->preferred_at->toDateTimeString(),
                'proposed_starts_at' => $r->proposed_starts_at?->toDateTimeString(),
                'session_id' => $r->session_id,
                'session_status' => $r->session?->status,
            ])
            ->values()
            ->all();
    }

    /**
     * Client turns down the proposed time — releases the (unpaid) hold the
     * same way any other pending_payment session is cancelled.
     */
    public function declineByClient(User $user, $request_id): TherapistSessionRequest
    {
        $request = TherapistSessionRequest::with('therapist.user')->find($request_id);

        if (empty($request) || $request->user_id != $user->id) {
            throw new ModelNotFoundException("Request not found");
        }

        if ($request->status != SessionConstants::REQUEST_PROPOSED) {
            throw new InvalidRequestException("This request has no proposed time to decline.");
        }

        if (!empty($request->session_id)) {
            (new SessionLifecycleService)->cancel($user, $request->session_id, []);
        }

        $request->update([
            'status' => SessionConstants::REQUEST_DECLINED,
            'responded_at' => now(),
        ]);

        if (!empty($request->therapist?->user)) {
            Notification::send($request->therapist->user, new TherapistSessionRequestNotification($request, 'declined_by_client'));
        }

        return $request;
    }
}
