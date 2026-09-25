<?php

namespace App\Services\PlatformAdmin;

use App\Constants\Business\OrganizationConstants;
use App\Constants\Therapist\TherapistConstants;
use App\Models\AccountDeactivation;
use App\Models\Dispute;
use App\Models\Feedback;
use App\Models\Organization;
use App\Models\TherapistApplication;
use App\Models\TherapySession;
use App\Models\User;

/**
 * Real platform KPI aggregate — the legacy Admin\DashboardService only
 * counts users/posts/groups; this pulls the numbers a platform admin
 * actually needs across users, orgs, sessions and open queues.
 */
class PlatformDashboardService
{
    public static function overview(): array
    {
        $sessions_this_month = TherapySession::where('status', TherapistConstants::SESSION_COMPLETED)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year);

        return [
            "users" => [
                "total" => User::count(),
                "new_this_month" => User::whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)->count(),
            ],
            "organizations" => [
                "total" => Organization::count(),
                // Was comparing against "Active" (capitalized) — the real
                // column is lowercase ("active"), so this always read 0.
                "active" => Organization::where('status', OrganizationConstants::STATUS_ACTIVE)->count(),
            ],
            "sessions" => [
                "completed_this_month" => (clone $sessions_this_month)->count(),
                "revenue_this_month" => (float) (clone $sessions_this_month)->sum('amount'),
            ],
            "queues" => [
                "pending_feedback" => Feedback::where('status', 'Pending')->count(),
                "pending_deactivations" => AccountDeactivation::whereIn('status', ['Pending', 'Processing'])->count(),
                "open_disputes" => Dispute::where('status', 'Pending')->count(),
            ],
        ];
    }

    /** Sidebar badge counts — kept separate from overview() so the nav
     *  doesn't have to wait on the full dashboard query on every page. */
    public static function navCounts(): array
    {
        return [
            "pending_therapist_applications" => TherapistApplication::whereIn('status', [
                TherapistConstants::STATUS_SUBMITTED,
                TherapistConstants::STATUS_IN_REVIEW,
            ])->count(),
            "flagged_therapists" => PlatformPerformanceService::overview(true)['flagged_count'],
            "pending_deactivations" => AccountDeactivation::whereIn('status', ['Pending', 'Processing'])->count(),
            "open_disputes" => Dispute::where('status', 'Pending')->count(),
        ];
    }
}
