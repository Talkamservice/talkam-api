<?php

namespace App\Services\Therapist;

use App\Constants\Therapist\TherapistConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Models\TherapistReview;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * User-to-therapist session reviews (planning doc 07). One per completed
 * session; authors always display anonymised.
 */
class SessionReviewService
{
    public function create(User $user, $booking_id, array $data): TherapistReview
    {
        $validator = Validator::make($data, [
            'rating' => 'required|integer|between:1,5',
            'comment' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $session = SessionBookingService::getOwnedByUser($booking_id, $user);

        if ($session->status != TherapistConstants::SESSION_COMPLETED) {
            throw new InvalidRequestException("Only completed sessions can be reviewed.");
        }

        if (TherapistReview::where('session_id', $session->id)->exists()) {
            throw new InvalidRequestException("This session has already been reviewed.");
        }

        $validated = $validator->validated();

        return TherapistReview::create([
            'session_id' => $session->id,
            'user_id' => $user->id,
            'therapist_id' => $session->therapist_id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
        ]);
    }

    /**
     * Anonymised list — no name/username/user id ever leaves the server.
     */
    public static function listFor($therapist_id)
    {
        return TherapistReview::where('therapist_id', $therapist_id)->latest();
    }

    public static function anonymise(TherapistReview $review): array
    {
        return [
            'id' => $review->id,
            'rating' => $review->rating,
            'comment' => $review->comment,
            'author' => 'Anonymous',
            'created_at' => formatDate($review->created_at),
        ];
    }
}
