<?php

namespace App\Services\PlatformAdmin;

use App\Models\Organization;
use App\Models\TherapySession;
use App\Models\User;

/**
 * Coarse growth funnel from existing timestamps — NOT the design mockup's
 * full segment/cohort engine (that needs an events pipeline that doesn't
 * exist). Real numbers, deliberately shallow.
 */
class PlatformGrowthService
{
    public static function overview(string $range = "12w"): array
    {
        $weeks = (int) filter_var($range, FILTER_SANITIZE_NUMBER_INT) ?: 12;
        $since = now()->subWeeks($weeks)->startOfWeek();

        $signups = User::where('created_at', '>=', $since)
            ->selectRaw("YEARWEEK(created_at, 1) as yw, MIN(DATE(created_at)) as week_start, COUNT(*) as count")
            ->groupBy('yw')
            ->orderBy('yw')
            ->get(['week_start', 'count']);

        $org_signups = Organization::where('created_at', '>=', $since)
            ->selectRaw("YEARWEEK(created_at, 1) as yw, MIN(DATE(created_at)) as week_start, COUNT(*) as count")
            ->groupBy('yw')
            ->orderBy('yw')
            ->get(['week_start', 'count']);

        $first_session_user_ids = TherapySession::where('created_at', '>=', $since)
            ->distinct()
            ->pluck('user_id');

        return [
            "user_signups" => $signups,
            "organization_signups" => $org_signups,
            "funnel" => [
                "signed_up" => User::where('created_at', '>=', $since)->count(),
                "booked_first_session" => $first_session_user_ids->count(),
            ],
        ];
    }
}
