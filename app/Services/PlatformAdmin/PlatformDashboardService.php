<?php

namespace App\Services\PlatformAdmin;

use App\Constants\Business\OrganizationConstants;
use App\Constants\Therapist\TherapistConstants;
use App\Models\AccountDeactivation;
use App\Models\ActivityLog;
use App\Models\CustomPlanQuoteRequest;
use App\Models\Dispute;
use App\Models\Feedback;
use App\Models\Organization;
use App\Models\TherapistApplication;
use App\Models\TherapySession;
use App\Models\User;
use Carbon\Carbon;

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
            "kpis" => self::kpis(),
            "users_by_type" => PlatformUserService::typeCounts(),
            "monthly_chart" => self::monthlySessionsAndRevenue(),
            "latest_users" => self::latestUsers(),
            "recent_activity" => self::recentActivity(),
        ];
    }

    /**
     * The 4 headline cards. Users/Revenue compare this month's growth against
     * last month's; "Active Sessions MTD" compares month-to-date against the
     * same number of days into last month (a fair pace comparison, not a
     * partial-vs-full-month one). Pending Actions has no natural growth rate
     * (it's a queue size, not a cumulative total) — its delta is real too,
     * but it's "how many opened in the last 30 days", not the mockup's exact
     * "-5" framing, which would need a resolved/backlog snapshot this app
     * doesn't keep.
     */
    private static function kpis(): array
    {
        $now = now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfLastMonth = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $daysIntoMonth = $now->day;

        $totalUsers = User::count();
        $usersAtStartOfMonth = User::where('created_at', '<', $startOfMonth)->count();
        $newUsersThisMonth = $totalUsers - $usersAtStartOfMonth;
        $usersGrowthPercent = $usersAtStartOfMonth > 0 ? round(($newUsersThisMonth / $usersAtStartOfMonth) * 100, 1) : null;

        $activeSessionsMtd = TherapySession::active()->whereBetween('starts_at', [$startOfMonth, $now])->count();
        $activeSessionsSamePeriodLastMonth = TherapySession::active()->whereBetween('starts_at', [
            $startOfLastMonth,
            $startOfLastMonth->copy()->addDays($daysIntoMonth),
        ])->count();
        $sessionsGrowthPercent = $activeSessionsSamePeriodLastMonth > 0
            ? round((($activeSessionsMtd - $activeSessionsSamePeriodLastMonth) / $activeSessionsSamePeriodLastMonth) * 100, 1)
            : null;

        $revenueThisMonth = (float) (PlatformBillingService::overview()['mrr'] ?? 0);
        $revenueLastMonth = self::revenueForMonth($startOfLastMonth);
        $revenueGrowthPercent = $revenueLastMonth > 0
            ? round((($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100, 1)
            : null;

        $pendingActionsNow = self::pendingActionsCount();
        $pendingActionsOpenedThisMonth = self::pendingActionsOpenedSince($now->copy()->subDays(30));

        return [
            "total_users" => ["value" => $totalUsers, "change_percent" => $usersGrowthPercent],
            "active_sessions_mtd" => ["value" => $activeSessionsMtd, "change_percent" => $sessionsGrowthPercent],
            "platform_revenue" => ["value" => $revenueThisMonth, "change_percent" => $revenueGrowthPercent],
            "pending_actions" => ["value" => $pendingActionsNow, "opened_last_30_days" => $pendingActionsOpenedThisMonth],
        ];
    }

    private static function revenueForMonth(Carbon $monthStart): float
    {
        $monthEnd = $monthStart->copy()->endOfMonth();

        $consumer = (float) \App\Models\Payment::where('status', 'Completed')
            ->whereBetween('created_at', [$monthStart, $monthEnd])->sum('amount');
        $org = (float) \App\Models\OrganizationInvoice::where('status', \App\Models\OrganizationInvoice::STATUS_PAID)
            ->whereBetween('paid_at', [$monthStart, $monthEnd])->sum('amount');

        return $consumer + $org;
    }

    /** Same real queues navCounts() badges the sidebar with, summed into one number. */
    private static function pendingActionsCount(): int
    {
        return TherapistApplication::whereIn('status', [TherapistConstants::STATUS_SUBMITTED, TherapistConstants::STATUS_IN_REVIEW])->count()
            + AccountDeactivation::whereIn('status', ['Pending', 'Processing'])->count()
            + Dispute::where('status', 'Pending')->count()
            + CustomPlanQuoteRequest::where('status', PlatformCustomQuoteRequestService::STATUS_PENDING)->count()
            + Feedback::where('status', 'Pending')->count();
    }

    private static function pendingActionsOpenedSince(Carbon $since): int
    {
        return TherapistApplication::whereIn('status', [TherapistConstants::STATUS_SUBMITTED, TherapistConstants::STATUS_IN_REVIEW])
                ->where('submitted_at', '>=', $since)->count()
            + AccountDeactivation::whereIn('status', ['Pending', 'Processing'])->where('created_at', '>=', $since)->count()
            + Dispute::where('status', 'Pending')->where('created_at', '>=', $since)->count()
            + CustomPlanQuoteRequest::where('status', PlatformCustomQuoteRequestService::STATUS_PENDING)->where('created_at', '>=', $since)->count()
            + Feedback::where('status', 'Pending')->where('created_at', '>=', $since)->count();
    }

    /** Jan–Dec of the current year — months after "today" are naturally 0,
     *  not hidden, since they genuinely haven't happened yet. */
    private static function monthlySessionsAndRevenue(): array
    {
        $year = now()->year;

        $sessions = TherapySession::where('status', TherapistConstants::SESSION_COMPLETED)
            ->whereYear('created_at', $year)
            ->selectRaw('MONTH(created_at) as m, COUNT(*) as sessions, SUM(amount) as revenue')
            ->groupBy('m')
            ->get()
            ->keyBy('m');

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $row = $sessions->get($m);
            $months[] = [
                "month" => Carbon::createFromDate($year, $m, 1)->format('M'),
                "sessions" => (int) ($row->sessions ?? 0),
                "revenue" => (float) ($row->revenue ?? 0),
            ];
        }

        return $months;
    }

    /** Deliberately omits a "Platform" (mobile/web/both) column — see
     *  PlatformUserService's class docblock: registration_platform has no
     *  honest mobile/web/both signal, so it isn't shown here either. */
    private static function latestUsers(int $limit = 6): array
    {
        return User::latest()->limit($limit)->get()->map(fn (User $u) => [
            "id" => $u->id,
            "name" => trim("{$u->first_name} {$u->last_name}"),
            "email" => $u->email,
            "type" => PlatformUserService::typeOf($u),
            "created_at" => $u->created_at->toDateTimeString(),
        ])->all();
    }

    private static function recentActivity(int $limit = 5): array
    {
        return ActivityLog::with('user:id,first_name,last_name,email')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (ActivityLog $log) => [
                "id" => $log->id,
                "title" => $log->title,
                "description" => $log->description,
                "actor_name" => $log->user ? (trim("{$log->user->first_name} {$log->user->last_name}") ?: $log->user->email) : null,
                "created_at" => $log->created_at->toDateTimeString(),
            ])->all();
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
            "pending_custom_quotes" => CustomPlanQuoteRequest::where('status', PlatformCustomQuoteRequestService::STATUS_PENDING)->count(),
        ];
    }
}
