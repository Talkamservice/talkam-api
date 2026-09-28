<?php

namespace App\Services\PlatformAdmin;

use App\Constants\Therapist\TherapistConstants;
use App\Models\MoodCheckin;
use App\Models\Organization;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\TherapySession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Growth analytics from real timestamps only. The design mockup's full
 * AARRR/segment/cohort suite assumes an events pipeline (page views, therapist
 * browses, NPS surveys) that does not exist in this codebase — every method
 * here sticks to what real rows already prove happened, and each segment/stage
 * definition is documented next to its query so the proxy it stands in for is
 * never mistaken for the mockup's exact wording.
 */
class PlatformGrowthService
{
    /** Users we treat as "engaged" this window — same signal for retention, cohorts and segments. */
    private const ACTIVITY_TABLES = [
        ["model" => TherapySession::class, "date" => "created_at"],
        ["model" => MoodCheckin::class, "date" => "created_at"],
        ["model" => Post::class, "date" => "created_at"],
        ["model" => PostComment::class, "date" => "created_at"],
    ];

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

    /**
     * Acquisition/Activation/Retention/Revenue — Referral and NPS are dropped
     * entirely rather than shown with a fabricated number: nothing in this app
     * records referral source or collects an NPS survey today.
     */
    public static function aarrr(string $range = "12w"): array
    {
        $weeks = (int) filter_var($range, FILTER_SANITIZE_NUMBER_INT) ?: 12;
        $since = now()->subWeeks($weeks)->startOfWeek();

        $signups = User::where('created_at', '>=', $since)->count();
        $activated = User::where('created_at', '>=', $since)
            ->whereNotNull('onboarding_completed_at')
            ->count();

        $eligible = User::where('created_at', '<=', now()->subDays(30))->pluck('id');
        $retained = $eligible->isEmpty() ? 0 : self::usersActiveSince($eligible, now()->subDays(30))->count();

        $mrr = (float) (PlatformBillingService::overview()['mrr'] ?? 0);

        return [
            "acquisition" => [
                "label" => "Signed up",
                "value" => $signups,
                "window_weeks" => $weeks,
            ],
            "activation" => [
                "label" => "Completed onboarding",
                "value" => $activated,
                "rate_percent" => $signups > 0 ? round(($activated / $signups) * 100, 1) : 0,
                "definition" => "Share of the window's signups with onboarding_completed_at set.",
            ],
            "retention" => [
                "label" => "Active in the last 30 days",
                "value" => $retained,
                "eligible" => $eligible->count(),
                "rate_percent" => $eligible->isEmpty() ? 0 : round(($retained / $eligible->count()) * 100, 1),
                "definition" => "Of users who signed up 30+ days ago, the share with a session, mood check-in, post or comment in the last 30 days.",
            ],
            "revenue" => [
                "label" => "MRR",
                "value" => $mrr,
            ],
            "not_tracked" => ["Referral", "NPS"],
        ];
    }

    /**
     * Real 4-stage funnel. The mockup's App Opened / Signup Started / Therapist
     * Browsed / First Check-in stages need page-view or browse-event tracking
     * that doesn't exist — they're omitted rather than estimated.
     */
    public static function funnel(string $range = "12w"): array
    {
        $weeks = (int) filter_var($range, FILTER_SANITIZE_NUMBER_INT) ?: 12;
        $since = now()->subWeeks($weeks)->startOfWeek();

        $signedUpIds = User::where('created_at', '>=', $since)->pluck('id');
        $signedUp = $signedUpIds->count();

        $emailVerified = User::whereIn('id', $signedUpIds)->whereNotNull('email_verified_at')->count();

        $bookedIds = TherapySession::whereIn('user_id', $signedUpIds)->distinct()->pluck('user_id');
        $booked = $bookedIds->count();

        $completed = TherapySession::whereIn('user_id', $signedUpIds)
            ->where('status', TherapistConstants::SESSION_COMPLETED)
            ->distinct()
            ->pluck('user_id')
            ->count();

        $stages = [
            ["key" => "signed_up", "label" => "Signed up", "value" => $signedUp],
            ["key" => "email_verified", "label" => "Email verified", "value" => $emailVerified],
            ["key" => "session_booked", "label" => "Booked a session", "value" => $booked],
            ["key" => "session_completed", "label" => "Completed a session", "value" => $completed],
        ];

        foreach ($stages as &$stage) {
            $stage["rate_percent"] = $signedUp > 0 ? round(($stage["value"] / $signedUp) * 100, 1) : 0;
        }
        unset($stage);

        return [
            "window_weeks" => $weeks,
            "stages" => $stages,
            "not_tracked" => ["App opened", "Signup started", "Therapist browsed", "First check-in"],
        ];
    }

    /**
     * Weekly signup cohorts, retention measured by the same real activity
     * signal as aarrr()/segments(). A week that hasn't happened yet for a
     * cohort is returned as null, not 0% — right-censoring, not a fake floor.
     */
    public static function cohorts(int $weeks = 8): array
    {
        $now = now();
        $since = $now->copy()->subWeeks($weeks)->startOfWeek();

        $users = User::where('created_at', '>=', $since)->get(['id', 'created_at']);
        if ($users->isEmpty()) {
            return ["cohorts" => [], "weeks_tracked" => $weeks];
        }

        $activityByUser = self::activityByUser($users->pluck('id'));

        $grouped = $users->groupBy(fn ($u) => $u->created_at->copy()->startOfWeek()->toDateString())
            ->sortKeys();

        $cohorts = [];
        foreach ($grouped as $weekStart => $cohortUsers) {
            $cohortStart = Carbon::parse($weekStart)->startOfWeek();
            $size = $cohortUsers->count();
            $retention = [];

            for ($k = 0; $k <= 7; $k++) {
                $windowStart = $cohortStart->copy()->addWeeks($k);
                $windowEnd = $cohortStart->copy()->addWeeks($k + 1);

                if ($windowStart->gt($now)) {
                    $retention[] = null;
                    continue;
                }

                $retained = 0;
                foreach ($cohortUsers as $u) {
                    foreach ($activityByUser[$u->id] ?? [] as $d) {
                        if ($d->gte($windowStart) && $d->lt($windowEnd)) {
                            $retained++;
                            break;
                        }
                    }
                }

                $retention[] = $size > 0 ? round(($retained / $size) * 100, 1) : null;
            }

            $cohorts[] = [
                "cohort_week" => $weekStart,
                "size" => $size,
                "retention_percent" => $retention,
            ];
        }

        return ["cohorts" => $cohorts, "weeks_tracked" => $weeks];
    }

    /**
     * Six segments, each defined from real rows only. "Therapy-Ready" and
     * "Power Users" stand in for the mockup's browse-based/streak-based
     * wording with the closest real proxy available — see each definition.
     */
    public static function segments(): array
    {
        $now = now();
        $definitions = self::segmentDefinitions();
        $priorNow = $now->copy()->subDays(30);

        $out = [];
        foreach ($definitions as $key => $def) {
            $count = $def["query"]($now)->count();
            $priorCount = $def["query"]($priorNow)->count();
            $delta = $priorCount > 0
                ? round((($count - $priorCount) / $priorCount) * 100, 1)
                : ($count > 0 ? null : 0.0);

            $out[] = [
                "key" => $key,
                "label" => $def["label"],
                "description" => $def["description"],
                "count" => $count,
                "mom_change_percent" => $delta,
            ];
        }

        return $out;
    }

    public static function segmentUsers(string $key, int $page = 1, int $perPage = 20): array
    {
        $definitions = self::segmentDefinitions();
        if (!isset($definitions[$key])) {
            return ["data" => [], "total" => 0, "page" => $page, "per_page" => $perPage];
        }

        $query = $definitions[$key]["query"](now());
        $total = $query->count();

        $users = $query
            ->orderByDesc('users.created_at')
            ->forPage($page, $perPage)
            ->get(['users.id', 'users.first_name', 'users.last_name', 'users.email', 'users.created_at'])
            ->map(fn ($u) => [
                "id" => $u->id,
                "name" => trim("{$u->first_name} {$u->last_name}") ?: $u->email,
                "email" => $u->email,
                "signed_up_at" => $u->created_at,
            ]);

        return ["data" => $users, "total" => $total, "page" => $page, "per_page" => $perPage];
    }

    /** @param Collection<int> $userIds */
    private static function usersActiveSince(Collection $userIds, Carbon $since): Collection
    {
        $active = collect();

        $active = $active->merge(TherapySession::whereIn('user_id', $userIds)->where('created_at', '>=', $since)->distinct()->pluck('user_id'));
        $active = $active->merge(MoodCheckin::whereIn('user_id', $userIds)->where('created_at', '>=', $since)->distinct()->pluck('user_id'));
        $active = $active->merge(Post::whereIn('user_id', $userIds)->where('created_at', '>=', $since)->distinct()->pluck('user_id'));
        $active = $active->merge(PostComment::whereIn('user_id', $userIds)->where('created_at', '>=', $since)->distinct()->pluck('user_id'));

        return $active->unique();
    }

    /** @return array<int, array<int, Carbon>> user_id => every real activity timestamp we know about */
    private static function activityByUser(Collection $userIds): array
    {
        $byUser = [];

        foreach (self::ACTIVITY_TABLES as $table) {
            $rows = $table["model"]::whereIn('user_id', $userIds)->get(['user_id', $table["date"]]);
            foreach ($rows as $row) {
                $byUser[$row->user_id][] = Carbon::parse($row->{$table["date"]});
            }
        }

        return $byUser;
    }

    /**
     * Each definition is a closure over "as of" so segments() can recompute
     * the same logic 30 days back for a real MoM delta, and segmentUsers()
     * can reuse the exact same query for the "View users" list.
     *
     * @return array<string, array{label: string, description: string, query: callable(Carbon): \Illuminate\Database\Eloquent\Builder}>
     */
    private static function segmentDefinitions(): array
    {
        // Raw subquery closures (not Eloquent relations) deliberately — User
        // has no therapySessions/moodCheckins/postComments/therapistSessionRequests
        // relation defined, and adding one just for this page isn't warranted.
        return [
            "power_users" => [
                "label" => "Power Users",
                "description" => "3+ completed sessions, active in the community in the last 30 days",
                "query" => fn (Carbon $asOf) => User::query()
                    ->where('users.created_at', '<=', $asOf)
                    ->whereIn('users.id', function ($q) use ($asOf) {
                        $q->select('user_id')->from('therapy_sessions')
                            ->where('status', TherapistConstants::SESSION_COMPLETED)
                            ->where('created_at', '<=', $asOf)
                            ->groupBy('user_id')
                            ->havingRaw('count(*) >= 3');
                    })
                    ->where(function ($q) use ($asOf) {
                        $since = $asOf->copy()->subDays(30);
                        $q->whereIn('users.id', function ($sub) use ($asOf, $since) {
                            $sub->select('user_id')->from('posts')->whereBetween('created_at', [$since, $asOf]);
                        })->orWhereIn('users.id', function ($sub) use ($asOf, $since) {
                            $sub->select('user_id')->from('post_comments')->whereBetween('created_at', [$since, $asOf]);
                        });
                    }),
            ],
            "at_risk" => [
                "label" => "At-Risk (Churning)",
                "description" => "Had 1+ completed sessions, no activity in the last 14+ days",
                "query" => fn (Carbon $asOf) => User::query()
                    ->where('users.created_at', '<=', $asOf)
                    ->whereIn('users.id', function ($q) use ($asOf) {
                        $q->select('user_id')->from('therapy_sessions')
                            ->where('status', TherapistConstants::SESSION_COMPLETED)
                            ->where('created_at', '<=', $asOf);
                    })
                    ->whereNotIn('users.id', function ($q) use ($asOf) {
                        $q->select('user_id')->from('therapy_sessions')
                            ->whereBetween('created_at', [$asOf->copy()->subDays(14), $asOf]);
                    })
                    ->whereNotIn('users.id', function ($q) use ($asOf) {
                        $q->select('user_id')->from('mood_checkins')
                            ->whereBetween('created_at', [$asOf->copy()->subDays(14), $asOf]);
                    })
                    ->whereNotIn('users.id', function ($q) use ($asOf) {
                        $q->select('user_id')->from('posts')
                            ->whereBetween('created_at', [$asOf->copy()->subDays(14), $asOf]);
                    })
                    ->whereNotIn('users.id', function ($q) use ($asOf) {
                        $q->select('user_id')->from('post_comments')
                            ->whereBetween('created_at', [$asOf->copy()->subDays(14), $asOf]);
                    }),
            ],
            "new_activated" => [
                "label" => "New & Activated",
                "description" => "Signed up in the last 30 days and logged a first mood check-in",
                "query" => fn (Carbon $asOf) => User::query()
                    ->whereBetween('users.created_at', [$asOf->copy()->subDays(30), $asOf])
                    ->whereIn('users.id', function ($q) use ($asOf) {
                        $q->select('user_id')->from('mood_checkins')->where('created_at', '<=', $asOf);
                    }),
            ],
            "b2b_employees" => [
                "label" => "B2B Employees",
                "description" => "Active employee member of a business organization",
                "query" => fn (Carbon $asOf) => User::query()
                    ->where('users.created_at', '<=', $asOf)
                    ->whereIn('users.id', function ($q) use ($asOf) {
                        $q->select('user_id')->from('organization_members')
                            ->where('role', 'employee')
                            ->where('status', 'active')
                            ->where('created_at', '<=', $asOf);
                    }),
            ],
            "therapy_ready" => [
                "label" => "Therapy-Ready",
                "description" => "Requested a therapist but hasn't booked a session yet",
                "query" => fn (Carbon $asOf) => User::query()
                    ->where('users.created_at', '<=', $asOf)
                    ->whereIn('users.id', function ($q) use ($asOf) {
                        $q->select('user_id')->from('therapist_session_requests')
                            ->whereNull('session_id')->where('created_at', '<=', $asOf);
                    })
                    ->whereNotIn('users.id', function ($q) use ($asOf) {
                        $q->select('user_id')->from('therapy_sessions')->where('created_at', '<=', $asOf);
                    }),
            ],
            "community_only" => [
                "label" => "Community-Only",
                "description" => "Posted or commented, never booked a therapy session",
                "query" => fn (Carbon $asOf) => User::query()
                    ->where('users.created_at', '<=', $asOf)
                    ->where(function ($q) use ($asOf) {
                        $q->whereIn('users.id', function ($sub) use ($asOf) {
                            $sub->select('user_id')->from('posts')->where('created_at', '<=', $asOf);
                        })->orWhereIn('users.id', function ($sub) use ($asOf) {
                            $sub->select('user_id')->from('post_comments')->where('created_at', '<=', $asOf);
                        });
                    })
                    ->whereNotIn('users.id', function ($q) use ($asOf) {
                        $q->select('user_id')->from('therapy_sessions')->where('created_at', '<=', $asOf);
                    }),
            ],
        ];
    }
}
