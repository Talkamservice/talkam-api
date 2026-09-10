<?php

namespace App\Services\Business;

use App\Constants\Business\OrganizationConstants;
use App\Constants\Therapist\TherapistConstants;
use App\Models\EmployeeSelfCheck;
use App\Models\MoodCheckin;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\TherapySession;
use Illuminate\Support\Facades\DB;

/**
 * Everything an employer is allowed to know about their workforce.
 *
 * The whole class exists to make one rule impossible to forget: an employer sees
 * anonymised, company-wide numbers and NOTHING else. Two guarantees hold here:
 *
 *  1. Every derived figure passes through self::suppress(), which blanks it when
 *     fewer than config('business.aggregate_minimum_cohort') employees
 *     contributed. A suppressed figure returns null with suppressed=true, so the
 *     dashboard can say "not enough data yet" instead of showing a misleading 0.
 *
 *  2. No method here ever selects a user_id, a session id, a timestamp of an
 *     individual event, or any free text an employee or therapist wrote. The
 *     queries aggregate in SQL; individual rows never reach PHP.
 *
 * See planning-docs/web-api/03-admin-dashboard.md §0 for the deck panels that
 * were deliberately NOT built because they would have broken rule 1.
 */
class OrgAggregateService
{
    public static function cohortFloor(): int
    {
        return (int) config('business.aggregate_minimum_cohort');
    }

    /**
     * The single suppression gate. Any figure derived from employee behaviour
     * must come out of here.
     */
    public static function suppress($value, int $cohort): array
    {
        $enough = $cohort >= self::cohortFloor();

        return [
            'value' => $enough ? $value : null,
            'cohort' => $cohort,
            'suppressed' => !$enough,
        ];
    }

    /** Active member user ids — the cohort every aggregate is measured against. */
    public static function memberIds(Organization $organization): array
    {
        return OrganizationMember::where('organization_id', $organization->id)
            ->where('status', OrganizationConstants::MEMBER_ACTIVE)
            ->where('role', OrganizationConstants::ROLE_EMPLOYEE)
            ->pluck('user_id')
            ->all();
    }

    /* ── Overview ───────────────────────────────────────────────────────── */

    public static function overview(Organization $organization): array
    {
        $member_ids = self::memberIds($organization);
        $cohort = count($member_ids);

        $month_start = now()->startOfMonth();
        $last_month_start = $month_start->copy()->subMonth();

        $seats_total = OrganizationMember::where('organization_id', $organization->id)
            ->whereIn('status', [
                OrganizationConstants::MEMBER_ACTIVE,
                OrganizationConstants::MEMBER_INVITED,
            ])
            ->count();

        $joined_this_month = OrganizationMember::where('organization_id', $organization->id)
            ->where('status', OrganizationConstants::MEMBER_ACTIVE)
            ->where('activated_at', '>=', $month_start)
            ->count();

        // "Active" = took any private action this month. Counted as DISTINCT
        // users in SQL; no user id is returned.
        $active_this_month = empty($member_ids) ? 0 : (int) DB::table('mood_checkins')
            ->whereIn('user_id', $member_ids)
            ->where('checked_in_on', '>=', $month_start->toDateString())
            ->distinct()
            ->count('user_id');

        $sessions_this_month = self::sessionCount($member_ids, $month_start, now());
        $sessions_last_month = self::sessionCount($member_ids, $last_month_start, $month_start);

        return [
            'cohort' => $cohort,
            'cohort_floor' => self::cohortFloor(),
            'kpis' => [
                'employees' => [
                    'value' => $seats_total,
                    'delta_label' => $joined_this_month > 0 ? "+{$joined_this_month} this month" : null,
                ],
                // Adoption is behavioural, so it is suppressed like everything else.
                'active_this_month' => array_merge(
                    self::suppress($active_this_month, $cohort),
                    [
                        'rate' => $cohort >= self::cohortFloor() && $cohort > 0
                            ? round(($active_this_month / $cohort) * 100, 1)
                            : null,
                    ]
                ),
                'sessions_this_month' => array_merge(
                    self::suppress($sessions_this_month, $cohort),
                    [
                        'delta_percent' => $sessions_last_month > 0 && $cohort >= self::cohortFloor()
                            ? (int) round((($sessions_this_month - $sessions_last_month) / $sessions_last_month) * 100)
                            : null,
                    ]
                ),
            ],
            'session_activity' => self::sessionActivity($member_ids, $cohort),
            'top_topics' => self::topTopics($organization, $member_ids, $cohort),
            'departments' => self::departmentRollup($organization),
            'roi' => self::roi($organization, $sessions_this_month, $cohort),
        ];
    }

    /**
     * The monthly usage-digest content (web §03 Settings → "Monthly usage
     * digest"). Deliberately separate from overview(): that method is
     * hard-wired to "this month so far" for the live dashboard, while a digest
     * reports a CLOSED prior period — reusing it would silently change what
     * the dashboard shows.
     */
    public static function monthlySummary(Organization $organization, $period_start, $period_end): array
    {
        $member_ids = self::memberIds($organization);
        $cohort = count($member_ids);
        $period_end_exclusive = $period_end->copy()->addDay()->startOfDay();

        $sessions_completed = self::sessionCount($member_ids, $period_start, $period_end_exclusive);

        $active_members = empty($member_ids) ? 0 : (int) DB::table('mood_checkins')
            ->whereIn('user_id', $member_ids)
            ->whereBetween('checked_in_on', [$period_start->toDateString(), $period_end->toDateString()])
            ->distinct()
            ->count('user_id');

        return [
            'seats_used' => $organization->seatsUsed(),
            'seats_total' => (int) $organization->seats_licensed,
            'sessions_completed' => self::suppress($sessions_completed, $cohort),
            'active_members' => self::suppress($active_members, $cohort),
            'top_topics' => self::topTopics($organization, $member_ids, $cohort, $period_start, $period_end),
        ];
    }

    private static function sessionCount(array $member_ids, $from, $to): int
    {
        if (empty($member_ids)) {
            return 0;
        }

        return TherapySession::whereIn('user_id', $member_ids)
            ->whereIn('status', [
                TherapistConstants::SESSION_COMPLETED,
                TherapistConstants::SESSION_CONFIRMED,
            ])
            ->where('starts_at', '>=', $from)
            ->where('starts_at', '<', $to)
            ->count();
    }

    /**
     * Sessions per day for the current month, as counts only.
     *
     * The deck draws twelve bars; each is a company-wide daily total, never a
     * list of sessions. Suppressed as a whole when the cohort is too small —
     * a daily count in a three-person company is a personal calendar.
     */
    private static function sessionActivity(array $member_ids, int $cohort): array
    {
        $start = now()->startOfMonth();
        $days = (int) now()->startOfMonth()->daysInMonth;

        $counts = empty($member_ids) ? collect() : TherapySession::query()
            ->whereIn('user_id', $member_ids)
            ->whereIn('status', [
                TherapistConstants::SESSION_COMPLETED,
                TherapistConstants::SESSION_CONFIRMED,
            ])
            ->where('starts_at', '>=', $start)
            ->selectRaw('DATE(starts_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i)->toDateString();
            $series[] = ['date' => $date, 'sessions' => (int) ($counts[$date] ?? 0)];
        }

        return self::suppress($series, $cohort);
    }

    /**
     * What the workforce is bringing to the platform, as percentages.
     *
     * Read from mood-check-in FACTORS in aggregate — never a member's note, and
     * never joined back to a user id.
     */
    /**
     * $from/$to scope the window explicitly (e.g. a closed digest period);
     * omitted, it falls back to the original rolling-90-day trend the live
     * dashboard has always shown — that default is untouched so overview()'s
     * existing call site behaves exactly as before.
     */
    private static function topTopics(Organization $organization, array $member_ids, int $cohort, $from = null, $to = null): array
    {
        if (empty($member_ids)) {
            return self::suppress([], $cohort);
        }

        $from ??= now()->subDays(90);

        $query = MoodCheckin::whereIn('user_id', $member_ids)
            ->where('checked_in_on', '>=', $from->toDateString())
            ->whereNotNull('factors');

        if ($to) {
            $query->where('checked_in_on', '<=', $to->toDateString());
        }

        $rows = $query->get(['factors']);

        $labels = collect(config('v2.checkins.factors'))->pluck('label', 'key');
        $counts = [];
        $total = 0;

        foreach ($rows as $row) {
            foreach ($row->factors ?? [] as $key) {
                $counts[$key] = ($counts[$key] ?? 0) + 1;
                $total++;
            }
        }

        if ($total === 0) {
            return self::suppress([], $cohort);
        }

        arsort($counts);

        $topics = collect($counts)
            ->take(4)
            ->map(fn ($count, $key) => [
                'key' => $key,
                'label' => $labels[$key] ?? $key,
                'percent' => (int) round(($count / $total) * 100),
            ])
            ->values()
            ->all();

        $named = array_sum(array_column($topics, 'percent'));
        if ($named < 100) {
            $topics[] = ['key' => 'other', 'label' => 'Other', 'percent' => 100 - $named];
        }

        return self::suppress($topics, $cohort);
    }

    /**
     * Department rollup — the honest replacement for the deck's per-session
     * table (see the plan's §0).
     *
     * Suppressed PER DEPARTMENT: a five-person company with a one-person Legal
     * team must not learn that Legal booked three sessions.
     */
    public static function departmentRollup(Organization $organization): array
    {
        $members = OrganizationMember::where('organization_id', $organization->id)
            ->where('status', OrganizationConstants::MEMBER_ACTIVE)
            ->where('role', OrganizationConstants::ROLE_EMPLOYEE)
            ->get(['user_id', 'department']);

        $month_start = now()->startOfMonth();
        $rollup = [];

        foreach ($members->groupBy(fn ($m) => $m->department ?: 'Unassigned') as $department => $group) {
            $ids = $group->pluck('user_id')->all();
            $size = count($ids);

            $rollup[] = array_merge(
                self::suppress(self::sessionCount($ids, $month_start, now()), $size),
                ['department' => $department, 'members' => $size]
            );
        }

        usort($rollup, fn ($a, $b) => $b['members'] <=> $a['members']);

        return $rollup;
    }

    /**
     * ROI. Every coefficient is config, and the only live input is the
     * (already suppressed) company-wide session count.
     */
    private static function roi(Organization $organization, int $sessions, int $cohort): array
    {
        $days_per_session = (float) config('business.roi.absenteeism_days_per_session');
        $daily_value = (float) config('business.roi.avg_daily_productivity_value');

        $quote = OrganizationPricingService::quoteFor($organization);
        $program_cost = (float) $quote['total_monthly'];
        $gross = $sessions * $days_per_session * $daily_value;

        return array_merge(
            self::suppress([
                'sessions' => $sessions,
                'days_per_session' => $days_per_session,
                'daily_value' => $daily_value,
                'gross_value' => round($gross, 2),
                'program_cost' => $program_cost,
                'net_roi' => round($gross - $program_cost, 2),
                'multiple' => $program_cost > 0 ? round($gross / $program_cost, 1) : null,
            ], $cohort),
            ['currency' => config('business.currency')]
        );
    }

    /* ── Team needs (onboarding self-check) ─────────────────────────────── */

    /**
     * The aggregate the self-check screen promised: "only an anonymised,
     * company-wide pattern (once at least 5 people respond) helps HR know which
     * specialties to prioritise."
     *
     * The cohort here is RESPONDENTS, not members — five members of whom two
     * answered is a two-person aggregate and stays suppressed.
     */
    public static function teamNeeds(Organization $organization): array
    {
        $respondents = EmployeeSelfCheck::where('organization_id', $organization->id)
            ->distinct()
            ->count('user_id');

        $rows = EmployeeSelfCheck::where('organization_id', $organization->id)
            ->selectRaw('category, SUM(score) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        $sum = (int) $rows->sum();

        if ($sum === 0) {
            return self::suppress([], $respondents);
        }

        $needs = collect(OrganizationConstants::SELF_CHECK_CATEGORIES)
            ->map(fn ($category) => [
                'category' => $category,
                'label' => OrganizationConstants::SELF_CHECK_CONCERNS[$category] ?? $category,
                'percent' => (int) round(((int) ($rows[$category] ?? 0) / $sum) * 100),
            ])
            ->sortByDesc('percent')
            ->values()
            ->all();

        return self::suppress($needs, $respondents);
    }

    /* ── Reports ────────────────────────────────────────────────────────── */

    /** Company-wide monthly session totals for the last six months. */
    public static function monthlyTrend(Organization $organization, int $months = 6): array
    {
        $member_ids = self::memberIds($organization);
        $cohort = count($member_ids);
        $series = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $start = now()->startOfMonth()->subMonths($i);
            $end = $start->copy()->addMonth();

            $series[] = [
                'label' => $start->format('M'),
                'month' => $start->format('Y-m'),
                'value' => self::sessionCount($member_ids, $start, $end),
            ];
        }

        return self::suppress($series, $cohort);
    }
}
