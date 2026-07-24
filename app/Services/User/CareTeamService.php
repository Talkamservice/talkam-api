<?php

namespace App\Services\User;

use App\Constants\Therapist\TherapistConstants;
use App\Models\TherapySession;
use App\Models\User;
use App\Services\Therapist\SessionNoteService;

/**
 * The member's "Your care team" card: who they are seeing, how many sessions
 * they have had together, and the continuity note.
 *
 * The note is the therapist's SHARED session note only (§11 already gates
 * sharing behind an explicit toggle) — a private clinical note is never
 * surfaced here.
 */
class CareTeamService
{
    /** Sessions that count as "seeing this therapist". */
    private const ACTIVE_STATUSES = [
        TherapistConstants::SESSION_CONFIRMED,
        TherapistConstants::SESSION_IN_PROGRESS,
        TherapistConstants::SESSION_COMPLETED,
    ];

    public static function forUser(User $user): array
    {
        $latest = TherapySession::with('therapist.user')
            ->where('user_id', $user->id)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->orderByDesc('starts_at')
            ->first();

        if (empty($latest) || empty($latest->therapist)) {
            return [
                'therapist' => null,
                'continuity_note' => null,
                'next_session_id' => null,
            ];
        }

        $therapist = $latest->therapist;

        $together = TherapySession::where('user_id', $user->id)
            ->where('therapist_id', $therapist->id)
            ->where('status', TherapistConstants::SESSION_COMPLETED)
            ->count();

        $next = TherapySession::where('user_id', $user->id)
            ->where('starts_at', '>', now())
            ->where('status', TherapistConstants::SESSION_CONFIRMED)
            ->orderBy('starts_at')
            ->first();

        return [
            'therapist' => [
                'id' => $therapist->id,
                'name' => $therapist->user?->full_name,
                'initials' => self::initials($therapist->user?->full_name),
                'avatar' => $therapist->user?->avatar,
                'credential_type' => $therapist->credential_type,
                'focus' => self::focus($therapist),
                'rating' => self::rating($therapist),
                'sessions_together' => $together,
            ],
            'continuity_note' => self::continuityNote($user, $therapist->id),
            'next_session_id' => $next?->id,
        ];
    }

    /** The most recent shared note from a completed session with this therapist. */
    private static function continuityNote(User $user, $therapist_id): ?string
    {
        $sessions = TherapySession::where('user_id', $user->id)
            ->where('therapist_id', $therapist_id)
            ->where('status', TherapistConstants::SESSION_COMPLETED)
            ->orderByDesc('starts_at')
            ->get();

        foreach ($sessions as $session) {
            $shared = SessionNoteService::sharedNoteFor($session);

            if (!empty($shared['content'])) {
                return $shared['content'];
            }
        }

        return null;
    }

    private static function rating($therapist): ?float
    {
        $average = $therapist->reviews()->avg('rating');

        return $average ? round((float) $average, 1) : null;
    }

    /**
     * "Dr. Adewale K." → "AK": drop honorifics, take the first letter of each
     * remaining word.
     */
    private static function initials(?string $name): ?string
    {
        if (empty($name)) {
            return null;
        }

        return collect(preg_split('/\s+/', trim($name)))
            ->reject(fn ($w) => in_array(rtrim(strtolower($w), '.'), ['dr', 'mr', 'mrs', 'ms', 'prof'], true))
            ->map(fn ($w) => strtoupper(mb_substr($w, 0, 1)))
            ->take(2)
            ->implode('');
    }

    /** Specialty names joined the way the card renders them. */
    private static function focus($therapist): ?string
    {
        $application = $therapist->user?->therapistApplications()
            ->where('status', TherapistConstants::STATUS_APPROVED)
            ->with('specialties.category')
            ->latest()
            ->first();

        if (empty($application)) {
            return null;
        }

        $names = $application->specialties
            ->map(fn ($s) => $s->category?->name)
            ->filter()
            ->take(2);

        return $names->isNotEmpty() ? $names->implode(' · ') : null;
    }
}
