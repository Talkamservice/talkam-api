<?php

namespace App\Services\Therapist;

use App\Constants\Therapist\TherapistConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\ClientTreatmentPlan;
use App\Models\SessionNote;
use App\Models\Therapist;
use App\Models\TherapySession;
use App\Models\User;
use App\Models\UserInterest;

/**
 * "Client" is derived, never stored: a user with >=1 confirmed/completed
 * session with the therapist (planning doc 11 decision — no Client model).
 */
class TherapistClientService
{
    const CLIENT_STATUSES = [
        TherapistConstants::SESSION_CONFIRMED,
        TherapistConstants::SESSION_COMPLETED,
    ];

    public static function clientIds(Therapist $therapist)
    {
        return TherapySession::where('therapist_id', $therapist->id)
            ->whereIn('status', self::CLIENT_STATUSES)
            ->distinct()
            ->pluck('user_id');
    }

    public static function roster(Therapist $therapist): array
    {
        return User::whereIn('id', self::clientIds($therapist))
            ->get()
            ->map(fn ($client) => [
                'id' => $client->id,
                'name' => $client->full_name,
                'username' => $client->username,
                'avatar' => $client->avatar,
                'topics' => self::topics($client),
                'sessions_count' => TherapySession::where('therapist_id', $therapist->id)
                    ->where('user_id', $client->id)
                    ->whereIn('status', self::CLIENT_STATUSES)
                    ->count(),
            ])
            ->values()
            ->all();
    }

    public static function clientOf(Therapist $therapist, $user_id): User
    {
        if (!self::clientIds($therapist)->contains((int) $user_id)) {
            throw new ModelNotFoundException("Client not found");
        }

        return User::findOrFail($user_id);
    }

    public static function details(Therapist $therapist, $user_id): array
    {
        $client = self::clientOf($therapist, $user_id);

        $sessions = TherapySession::with('review')
            ->where('therapist_id', $therapist->id)
            ->where('user_id', $client->id)
            ->whereIn('status', self::CLIENT_STATUSES)
            ->orderBy('starts_at')
            ->get();

        $completed = $sessions->where('status', TherapistConstants::SESSION_COMPLETED)->count();

        $plan = ClientTreatmentPlan::where([
            'therapist_id' => $therapist->id,
            'user_id' => $client->id,
        ])->first();

        $notes = SessionNote::whereIn('session_id', $sessions->pluck('id'))
            ->get()
            ->keyBy('session_id');

        return [
            'id' => $client->id,
            'name' => $client->full_name,
            'topics' => self::topics($client),
            'client_since' => formatDate($sessions->first()?->starts_at),
            'sessions_count' => $sessions->count(),
            'completed_sessions' => $completed,
            'treatment_plan' => empty($plan) ? null : [
                'total_sessions' => $plan->total_sessions,
                'progress_status' => $plan->progress_status,
                'progress' => $plan->total_sessions > 0
                    ? round($completed / $plan->total_sessions, 2)
                    : 0,
                'notes' => $plan->notes,
            ],
            'session_history' => $sessions->map(fn ($session) => [
                'id' => $session->id,
                'starts_at' => $session->starts_at->toDateTimeString(),
                'status' => $session->status,
                'format' => $session->format,
                'summary' => $notes[$session->id]->title ?? null,
            ])->values()->all(),
            // Non-blocking note prompt: last completed session lacks a note.
            'needs_note' => $sessions->last(fn ($s) => $s->status == TherapistConstants::SESSION_COMPLETED)
                && empty($notes[$sessions->last(fn ($s) => $s->status == TherapistConstants::SESSION_COMPLETED)->id] ?? null),
        ];
    }

    private static function topics(User $client): array
    {
        return UserInterest::with('category')
            ->where('user_id', $client->id)
            ->get()
            ->map(fn ($row) => $row->category?->name)
            ->filter()
            ->values()
            ->all();
    }
}
