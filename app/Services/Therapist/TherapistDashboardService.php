<?php

namespace App\Services\Therapist;

use App\Constants\Business\OrganizationConstants;
use App\Constants\Therapist\TherapistConstants;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\SessionNote;
use App\Models\Therapist;
use App\Models\TherapistReview;
use App\Models\TherapySession;
use App\Models\User;

/**
 * The therapist "cockpit" aggregates: Home and Analytics.
 *
 * Both are the therapist's OWN numbers, and both keep the deck's client
 * anonymity — a client is a short ref and a focus area, never a name plucked
 * from another surface. Continuity notes are the therapist's own shared notes,
 * the same §11 boundary the mobile app already enforces.
 */
class TherapistDashboardService
{
    /* ── Business-employed branch ───────────────────────────────────────── */

    /**
     * A therapist who accepted an org invite (§01) is paid by that company, not
     * by TalkAM — so the Earnings module is hidden and a "paid by business" tag
     * is shown instead.
     */
    public static function employment(User $user): array
    {
        $membership = $user->organizationMemberships()
            ->where('role', OrganizationConstants::ROLE_THERAPIST)
            ->where('status', OrganizationConstants::MEMBER_ACTIVE)
            ->with('organization')
            ->first();

        return [
            'is_business_employed' => !empty($membership),
            'employer_name' => $membership?->organization?->name,
        ];
    }

    /* ── Home ───────────────────────────────────────────────────────────── */

    public static function home(Therapist $therapist): array
    {
        $user = $therapist->user;

        $next = TherapySession::with('user')
            ->where('therapist_id', $therapist->id)
            ->where('status', TherapistConstants::SESSION_CONFIRMED)
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->first();

        return [
            'next_session' => $next ? self::sessionCard($next, $therapist) : null,
            'attention' => self::attention($therapist),
            'kpis' => self::homeKpis($therapist),
            'continuity' => self::continuity($therapist),
            'latest_review' => self::latestReview($therapist),
            'self_care' => self::selfCareNudge($therapist),
            'employment' => self::employment($user),
        ];
    }

    /** Counts behind the "needs your attention" strip. */
    private static function attention(Therapist $therapist): array
    {
        $pending_notes = TherapySession::where('therapist_id', $therapist->id)
            ->where('status', TherapistConstants::SESSION_COMPLETED)
            ->whereDoesntHave('note', fn ($q) => $q->where('status', 'final'))
            ->count();

        $reschedule_requests = \App\Models\SessionReschedule::whereHas(
            'session',
            fn ($q) => $q->where('therapist_id', $therapist->id)
        )
            ->where('status', \App\Constants\Therapist\SessionConstants::RESCHEDULE_PENDING)
            ->count();

        $unread_messages = self::unreadMessageCount($therapist->user_id);

        return [
            'pending_notes' => $pending_notes,
            'reschedule_requests' => $reschedule_requests,
            'unread_messages' => $unread_messages,
        ];
    }

    private static function unreadMessageCount(int $user_id): int
    {
        return Message::where('receiver_id', $user_id)
            ->whereNull('read_at')
            ->count();
    }

    private static function homeKpis(Therapist $therapist): array
    {
        $this_week = TherapySession::where('therapist_id', $therapist->id)
            ->where('status', TherapistConstants::SESSION_COMPLETED)
            ->where('starts_at', '>=', now()->startOfWeek())
            ->count();

        $completed = TherapySession::where('therapist_id', $therapist->id)
            ->where('status', TherapistConstants::SESSION_COMPLETED)
            ->count();

        $rating = $therapist->reviews()->avg('rating');

        $notes_due = TherapySession::where('therapist_id', $therapist->id)
            ->where('status', TherapistConstants::SESSION_COMPLETED)
            ->whereDoesntHave('note', fn ($q) => $q->where('status', 'final'))
            ->count();

        return [
            'sessions_this_week' => $this_week,
            'rating' => $rating ? round((float) $rating, 1) : null,
            'notes_due' => $notes_due,
            'utilisation' => self::utilisation($therapist),
            'sessions_completed' => $completed,
        ];
    }

    /**
     * Booked minutes over available minutes for the current week. Available
     * comes from the recurring availability grid.
     */
    private static function utilisation(Therapist $therapist): ?int
    {
        $slots = \App\Models\TherapistAvailability::where('user_id', $therapist->user_id)
            ->where('active', true)
            ->count();

        if ($slots === 0) {
            return null;
        }

        $booked = TherapySession::where('therapist_id', $therapist->id)
            ->whereIn('status', [
                TherapistConstants::SESSION_CONFIRMED,
                TherapistConstants::SESSION_COMPLETED,
            ])
            ->where('starts_at', '>=', now()->startOfWeek())
            ->where('starts_at', '<', now()->endOfWeek())
            ->count();

        return (int) min(100, round(($booked / $slots) * 100));
    }

    /** Recent clients with a next appointment — the continuity-of-care panel. */
    private static function continuity(Therapist $therapist, int $limit = 4): array
    {
        return TherapySession::with('user')
            ->where('therapist_id', $therapist->id)
            ->where('status', TherapistConstants::SESSION_CONFIRMED)
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->limit($limit)
            ->get()
            ->map(function ($s) use ($therapist) {
                $last_completed = self::lastCompleted($therapist->id, $s->user_id);

                return [
                    'client_ref' => self::clientRef($s->user_id),
                    'focus' => self::focusFor($s),
                    'next_at' => $s->starts_at->toDateTimeString(),
                    'shared_note' => $last_completed
                        ? SessionNoteService::sharedNoteFor($last_completed)['content'] ?? null
                        : null,
                ];
            })
            ->values()
            ->all();
    }

    private static function lastCompleted($therapist_id, $user_id): ?TherapySession
    {
        return TherapySession::where('therapist_id', $therapist_id)
            ->where('user_id', $user_id)
            ->where('status', TherapistConstants::SESSION_COMPLETED)
            ->orderByDesc('starts_at')
            ->first();
    }

    private static function latestReview(Therapist $therapist): ?array
    {
        $review = TherapistReview::where('therapist_id', $therapist->id)
            ->latest()
            ->first();

        if (empty($review)) {
            return null;
        }

        return [
            'stars' => (int) $review->rating,
            'text' => $review->comment,
            'when' => $review->created_at?->diffForHumans(),
        ];
    }

    private static function selfCareNudge(Therapist $therapist): ?string
    {
        $this_week = TherapySession::where('therapist_id', $therapist->id)
            ->where('status', TherapistConstants::SESSION_COMPLETED)
            ->where('starts_at', '>=', now()->startOfWeek())
            ->count();

        $threshold = (int) config('therapist.self_care_session_threshold', 15);

        if ($this_week < $threshold) {
            return null;
        }

        return "You've run {$this_week} sessions this week. Blocking one recovery slot "
            . "keeps your practice sustainable.";
    }

    /* ── Analytics ──────────────────────────────────────────────────────── */

    public static function analytics(Therapist $therapist, string $range = '4w'): array
    {
        $windows = config('therapist.analytics_windows');
        $days = $windows[$range] ?? $windows['4w'] ?? 28;
        $since = now()->subDays($days);

        $sessions = TherapySession::where('therapist_id', $therapist->id)
            ->where('starts_at', '>=', $since)
            ->get();

        $completed = $sessions->where('status', TherapistConstants::SESSION_COMPLETED);
        $no_shows = $sessions->where('status', TherapistConstants::SESSION_NO_SHOW);

        return [
            'range' => $range,
            'window_days' => $days,
            'kpis' => [
                'total_sessions' => $completed->count(),
                'completion_rate' => $sessions->count()
                    ? (int) round(($completed->count() / $sessions->count()) * 100)
                    : null,
                'no_shows' => $no_shows->count(),
                'repeat_clients_percent' => self::repeatClientsPercent($therapist, $since),
                'active_clients' => $completed->pluck('user_id')->unique()->count(),
                'avg_rating' => self::rangeRating($therapist, $since),
            ],
            'session_bars' => self::sessionBars($therapist, $days),
            'top_topics' => self::topTopics($completed),
            'outcome_trend' => self::outcomeTrend($therapist, $days),
            'rating_breakdown' => self::ratingBreakdown($therapist, $since),
            'busiest_slots' => self::busiestSlots($therapist, $since),
        ];
    }

    private static function repeatClientsPercent(Therapist $therapist, $since): ?int
    {
        $counts = TherapySession::where('therapist_id', $therapist->id)
            ->where('status', TherapistConstants::SESSION_COMPLETED)
            ->where('starts_at', '>=', $since)
            ->selectRaw('user_id, COUNT(*) as total')
            ->groupBy('user_id')
            ->pluck('total');

        if ($counts->isEmpty()) {
            return null;
        }

        $repeat = $counts->filter(fn ($n) => $n > 1)->count();

        return (int) round(($repeat / $counts->count()) * 100);
    }

    private static function rangeRating(Therapist $therapist, $since): ?float
    {
        $avg = TherapistReview::where('therapist_id', $therapist->id)
            ->where('created_at', '>=', $since)
            ->avg('rating');

        return $avg ? round((float) $avg, 1) : null;
    }

    /** Weekly session counts across the window. */
    private static function sessionBars(Therapist $therapist, int $days): array
    {
        $weeks = max(1, (int) ceil($days / 7));
        $bars = [];

        for ($i = $weeks - 1; $i >= 0; $i--) {
            $start = now()->subWeeks($i)->startOfWeek();
            $end = $start->copy()->endOfWeek();

            $bars[] = TherapySession::where('therapist_id', $therapist->id)
                ->where('status', TherapistConstants::SESSION_COMPLETED)
                ->whereBetween('starts_at', [$start, $end])
                ->count();
        }

        return $bars;
    }

    /** Client focus areas across completed sessions, as percentages. */
    private static function topTopics($completed): array
    {
        $counts = [];

        foreach ($completed as $session) {
            $focus = self::focusFor($session);
            if ($focus) {
                $counts[$focus] = ($counts[$focus] ?? 0) + 1;
            }
        }

        $total = array_sum($counts);
        if ($total === 0) {
            return [];
        }

        arsort($counts);

        return collect($counts)
            ->take(3)
            ->map(fn ($count, $label) => [
                'label' => $label,
                'percent' => (int) round(($count / $total) * 100),
            ])
            ->values()
            ->all();
    }

    /**
     * Outcome = mean post-session client mood per week (reusing §02's
     * client_post_mood), so it is a real signal, not an invented curve.
     */
    private static function outcomeTrend(Therapist $therapist, int $days): array
    {
        $weeks = max(1, (int) ceil($days / 7));
        $trend = [];

        for ($i = $weeks - 1; $i >= 0; $i--) {
            $start = now()->subWeeks($i)->startOfWeek();
            $end = $start->copy()->endOfWeek();

            $avg = TherapySession::where('therapist_id', $therapist->id)
                ->whereNotNull('client_post_mood')
                ->whereBetween('starts_at', [$start, $end])
                ->avg('client_post_mood');

            $trend[] = $avg ? round((float) $avg, 2) : null;
        }

        return $trend;
    }

    private static function ratingBreakdown(Therapist $therapist, $since): array
    {
        $reviews = TherapistReview::where('therapist_id', $therapist->id)
            ->where('created_at', '>=', $since)
            ->get();

        $total = $reviews->count();

        return collect([5, 4, 3, 2, 1])->map(function ($stars) use ($reviews, $total) {
            $count = $reviews->where('rating', $stars)->count();

            return [
                'stars' => $stars,
                'count' => $count,
                'percent' => $total ? (int) round(($count / $total) * 100) : 0,
            ];
        })->values()->all();
    }

    /** Session load by weekday across the window, as a 0–100 heat value. */
    private static function busiestSlots(Therapist $therapist, $since): array
    {
        $counts = TherapySession::where('therapist_id', $therapist->id)
            ->where('status', TherapistConstants::SESSION_COMPLETED)
            ->where('starts_at', '>=', $since)
            ->get()
            ->groupBy(fn ($s) => $s->starts_at->dayOfWeekIso) // 1=Mon .. 7=Sun
            ->map->count();

        $peak = max($counts->max() ?: 1, 1);
        $labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

        return collect(range(1, 7))->map(fn ($iso) => [
            'day' => $labels[$iso - 1],
            'load' => (int) round((($counts[$iso] ?? 0) / $peak) * 100),
        ])->values()->all();
    }

    /* ── Shared helpers ─────────────────────────────────────────────────── */

    private static function sessionCard(TherapySession $session, Therapist $therapist): array
    {
        $last_completed = self::lastCompleted($therapist->id, $session->user_id);

        return [
            'id' => $session->id,
            'client_ref' => self::clientRef($session->user_id),
            'starts_at' => $session->starts_at->toDateTimeString(),
            'format' => $session->format,
            'duration_minutes' => $session->duration_minutes,
            'focus' => self::focusFor($session),
            'last_note' => $last_completed
                ? SessionNoteService::sharedNoteFor($last_completed)['content'] ?? null
                : null,
            'session_number' => self::sessionNumber($session),
            'pending_reschedule' => SessionBookingService::pendingReschedule($session),
        ];
    }

    /** Stable pseudonymous client ref — "#4021". */
    private static function clientRef(?int $user_id): string
    {
        return '#' . (4000 + ((int) $user_id % 6000));
    }

    /** The client's first interest topic, the deck's "focus" chip. */
    private static function focusFor(TherapySession $session): ?string
    {
        $topic = \App\Models\UserInterest::with('category')
            ->where('user_id', $session->user_id)
            ->first();

        return $topic?->category?->name;
    }

    private static function sessionNumber(TherapySession $session): string
    {
        $n = TherapySession::where('therapist_id', $session->therapist_id)
            ->where('user_id', $session->user_id)
            ->where('starts_at', '<=', $session->starts_at)
            ->count();

        return "Session {$n}";
    }
}
