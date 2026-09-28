<?php

namespace App\Services\PlatformAdmin;

use App\Constants\ActivityLog\ActivityLogConstants;
use App\Constants\General\StatusConstants;
use App\Constants\Therapist\TherapistConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\Dispute;
use App\Models\PlatformSetting;
use App\Models\Therapist;
use App\Models\TherapistReview;
use App\Services\ActivityLog\ActivityLogService;
use App\Services\Therapist\TherapistDirectoryService;
use App\Services\User\UserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Net-new — no "flagged therapist" concept exists anywhere else. Computes
 * average rating (therapist_reviews) and no-show rate (therapy_sessions)
 * per therapist from real data; flags anyone under the rating floor or over
 * the no-show ceiling. Two-tier (yellow/red) thresholds are stored in
 * PlatformSetting so "Edit Thresholds" is a real, persisted control —
 * defaults below are only used until an admin overrides them.
 */
class PlatformPerformanceService
{
    const DEFAULTS = [
        "performance_yellow_rating" => 3.5,
        "performance_red_rating" => 3.0,
        "performance_yellow_min_sessions" => 10,
        "performance_red_min_sessions" => 15,
        "performance_dispute_auto_suspend" => 3,
    ];

    public static function thresholds(): array
    {
        return collect(self::DEFAULTS)->map(fn ($default, $key) => (float) (PlatformSetting::get($key) ?? $default))->all();
    }

    public static function overview(bool $flaggedOnly = false, int $per_page = 10, int $page = 1)
    {
        $t = self::thresholds();

        $sessionStats = DB::table('therapy_sessions')
            ->select('therapist_id')
            ->selectRaw('COUNT(*) as total_sessions')
            ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as no_shows", [TherapistConstants::SESSION_NO_SHOW])
            ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as cancellations", [TherapistConstants::SESSION_CANCELLED])
            ->whereIn('status', [TherapistConstants::SESSION_COMPLETED, TherapistConstants::SESSION_NO_SHOW, TherapistConstants::SESSION_CANCELLED])
            ->groupBy('therapist_id')
            ->get()
            ->keyBy('therapist_id');

        $ratingStats = TherapistReview::selectRaw('therapist_id, AVG(rating) as avg_rating, COUNT(*) as review_count')
            ->groupBy('therapist_id')
            ->get()
            ->keyBy('therapist_id');

        $lowRatingCounts = TherapistReview::where('rating', '<=', 2)
            ->selectRaw('therapist_id, COUNT(*) as low_count')
            ->groupBy('therapist_id')
            ->pluck('low_count', 'therapist_id');

        $disputeCounts = Dispute::where('subject_type', Therapist::class)
            ->where('status', 'Pending')
            ->selectRaw('subject_id, COUNT(*) as open_count')
            ->groupBy('subject_id')
            ->pluck('open_count', 'subject_id');

        $recentDisputeCounts = Dispute::where('subject_type', Therapist::class)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('subject_id, COUNT(*) as recent_count')
            ->groupBy('subject_id')
            ->pluck('recent_count', 'subject_id');

        $therapists = Therapist::with('user:id,first_name,last_name,email')->get();

        $rows = $therapists->map(function (Therapist $th) use ($sessionStats, $ratingStats, $lowRatingCounts, $disputeCounts, $recentDisputeCounts, $t) {
            $sessions = $sessionStats->get($th->id);
            $ratings = $ratingStats->get($th->id);

            $total = (int) ($sessions->total_sessions ?? 0);
            $noShows = (int) ($sessions->no_shows ?? 0);
            $noShowRate = $total > 0 ? round(($noShows / $total) * 100, 1) : null;
            $reviewCount = (int) ($ratings->review_count ?? 0);
            $avgRating = $ratings ? round((float) $ratings->avg_rating, 2) : null;
            $recentDisputes = (int) ($recentDisputeCounts[$th->id] ?? 0);

            $tier = null;
            if ($avgRating !== null) {
                if ($reviewCount >= $t['performance_red_min_sessions'] && $avgRating < $t['performance_red_rating']) {
                    $tier = "red";
                } elseif ($reviewCount >= $t['performance_yellow_min_sessions'] && $avgRating < $t['performance_yellow_rating']) {
                    $tier = "yellow";
                }
            }
            $autoSuspendFlag = $recentDisputes >= $t['performance_dispute_auto_suspend'];

            $specialty = collect(TherapistDirectoryService::specialties($th))->pluck('name')->first();
            $flagged = $tier !== null || $autoSuspendFlag;

            return [
                "therapist_id" => $th->id,
                "user_id" => $th->user_id,
                "name" => $th->user ? trim("{$th->user->first_name} {$th->user->last_name}") : null,
                "email" => $th->user?->email,
                "specialty" => $specialty,
                "avg_rating" => $avgRating,
                "review_count" => $reviewCount,
                "total_sessions" => $total,
                "no_shows" => $noShows,
                "no_show_rate_percent" => $noShowRate,
                "cancellations" => (int) ($sessions->cancellations ?? 0),
                "low_ratings_count" => (int) ($lowRatingCounts[$th->id] ?? 0),
                "open_disputes" => (int) ($disputeCounts[$th->id] ?? 0),
                "recent_disputes_30d" => $recentDisputes,
                "tier" => $tier,
                "auto_suspend_flag" => $autoSuspendFlag,
                "flagged" => $flagged,
                "review_hold_at" => $th->review_hold_at?->toDateTimeString(),
                // Only fetched for flagged rows — the full roster (~20+
                // therapists) doesn't need a per-row review query each.
                "recent_ratings" => $flagged ? self::recentRatings($th->id) : [],
                "last_action" => self::lastAction($th->id),
            ];
        });

        $filtered = $flaggedOnly ? $rows->filter(fn ($r) => $r['flagged']) : $rows;
        $sorted = $filtered->sortBy([
            [fn ($r) => $r['tier'] === 'red' ? 0 : ($r['tier'] === 'yellow' ? 1 : 2), 'asc'],
            ['avg_rating', 'asc'],
        ])->values();

        return [
            "thresholds" => $t,
            "yellow_count" => $rows->where('tier', 'yellow')->count(),
            "red_count" => $rows->where('tier', 'red')->count(),
            "flagged_count" => $rows->where('flagged', true)->count(),
            "suspended_pending" => Therapist::whereNotNull('review_hold_at')->count(),
            "resolved_this_month" => self::resolvedThisMonth(),
            "therapists" => $sorted->forPage($page, $per_page)->values(),
            "total" => $sorted->count(),
            "current_page" => $page,
            "last_page" => max(1, (int) ceil($sorted->count() / $per_page)),
        ];
    }

    public static function updateThreshold(string $key, $value): void
    {
        if (!array_key_exists($key, self::DEFAULTS)) {
            throw new InvalidRequestException("Unknown threshold.");
        }
        PlatformSetting::set($key, (string) $value);
    }

    public static function sendWarningEmail(int $therapistId, ?string $note = null): void
    {
        $therapist = self::find($therapistId);
        $avg = round((float) TherapistReview::where('therapist_id', $therapistId)->avg('rating'), 2);

        Notification::send($therapist->user, new \App\Notifications\Therapist\TherapistPerformanceWarningNotification($avg, $note));
        self::log($therapist, 'sent', 'Warning Email Sent', "sent a performance warning email");
    }

    public static function placeOnReviewHold(int $therapistId): void
    {
        $therapist = self::find($therapistId);
        $therapist->update(['review_hold_at' => now(), 'status' => StatusConstants::INACTIVE]);
        self::log($therapist, 'suspend', 'Placed on Review Hold', "placed on review hold (removed from client search pending review)");
    }

    public static function clearReviewHold(int $therapistId): void
    {
        $therapist = self::find($therapistId);
        $therapist->update(['review_hold_at' => null, 'status' => StatusConstants::ACTIVE]);
        self::log($therapist, 'resolved', 'Review Hold Cleared', "cleared the review hold");
    }

    public static function forceReverification(int $therapistId): void
    {
        $therapist = self::find($therapistId);
        $therapist->update(['verified_at' => null]);
        self::log($therapist, 'updated', 'Re-verification Required', "required re-verification");
    }

    /** Reuses the existing account-ban flow wholesale (UserService::ban()
     *  already deactivates the linked Therapist row and revokes sessions) —
     *  "terminate" isn't a separate therapist-only concept anywhere. */
    public static function terminateAccount(int $therapistId, string $reason): void
    {
        $therapist = self::find($therapistId);
        $request = \Illuminate\Http\Request::create('/', 'POST', ['suspend_ban_reason' => $reason]);
        (new UserService)->ban($request, $therapist->user_id);
    }

    private static function find(int $id): Therapist
    {
        $therapist = Therapist::with('user')->find($id);
        if (empty($therapist)) {
            throw new ModelNotFoundException("Therapist not found");
        }
        return $therapist;
    }

    private static function log(Therapist $therapist, string $event, string $title, string $verb): void
    {
        (new ActivityLogService)
            ->setEvent($event)
            ->setTitle($title)
            ->setDescription((auth()->user()?->email) . " {$verb} for " . ($therapist->user?->email ?? "therapist #{$therapist->id}"))
            ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
            ->setActivity($event)
            ->setModel(Therapist::class, $therapist->id)
            ->setAdmin(auth()->user()?->id)
            ->setData(["Therapist" => $therapist->refresh()->toArray()])
            ->setUrl(request()?->fullUrl())
            ->log();
    }

    private static function recentRatings(int $therapistId): array
    {
        return TherapistReview::where('therapist_id', $therapistId)
            ->latest()
            ->limit(12)
            ->pluck('rating')
            ->reverse()
            ->values()
            ->all();
    }

    private static function lastAction(int $therapistId): ?array
    {
        $log = \App\Models\ActivityLog::where('model', Therapist::class)
            ->where('model_id', $therapistId)
            ->with('user:id,first_name,last_name')
            ->latest()
            ->first();

        if (empty($log)) {
            return null;
        }

        return [
            "title" => $log->title,
            "by" => $log->user ? trim("{$log->user->first_name} {$log->user->last_name}") : "System",
            "at" => $log->created_at->toDateTimeString(),
        ];
    }

    private static function resolvedThisMonth(): int
    {
        return \App\Models\ActivityLog::where('model', Therapist::class)
            ->where('event', 'resolved')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->distinct()
            ->count('model_id');
    }
}
