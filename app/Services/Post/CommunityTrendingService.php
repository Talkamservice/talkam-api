<?php

namespace App\Services\Post;

use App\Constants\General\StatusConstants;
use App\Constants\Post\PostCategoryConstants;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\User;
use App\Services\User\MuteService;

/**
 * "Trending topics this week" for the employee dashboard.
 *
 * Anonymity is the whole point of this surface: the payload carries a topic
 * name, a post count, ONE excerpt and a relative time — never an author id,
 * username or avatar. The deck renders it as "— anonymous · 2h ago".
 */
class CommunityTrendingService
{
    public static function topics(?User $user, int $days = 7, int $limit = 6): array
    {
        $since = now()->subDays($days);
        $excluded = self::excludedAuthorIds($user);

        $categories = PostCategory::where('type', PostCategoryConstants::TYPE_INTEREST_TOPIC)
            ->get()
            ->keyBy('id');

        if ($categories->isEmpty()) {
            return [];
        }

        $posts = Post::query()
            ->whereIn('category_id', $categories->keys())
            ->where('status', StatusConstants::ACTIVE)
            ->where('created_at', '>=', $since)
            ->when(!empty($excluded), fn ($q) => $q->whereNotIn('user_id', $excluded))
            ->orderByDesc('created_at')
            ->get(['id', 'category_id', 'title', 'body', 'created_at']);

        return $posts
            ->groupBy('category_id')
            ->map(function ($group, $category_id) use ($categories) {
                $latest = $group->first();

                return [
                    'category_id' => (int) $category_id,
                    'name' => $categories[$category_id]?->name,
                    'posts' => $group->count(),
                    // Excerpt only — never the whole post, never the author.
                    'snippet' => self::excerpt($latest->body ?? $latest->title),
                    'last_post_at' => $latest->created_at?->toDateTimeString(),
                    'time_ago' => $latest->created_at?->diffForHumans(null, true) . ' ago',
                ];
            })
            ->sortByDesc('posts')
            ->take($limit)
            ->values()
            ->all();
    }

    /** Muted (§04) and blocked (v1) authors never appear in the rollup. */
    private static function excludedAuthorIds(?User $user): array
    {
        if (empty($user)) {
            return [];
        }

        $muted = MuteService::mutedIds($user);
        $blocked = $user->blockedUsers()->pluck('blocked_user_id')->all();

        return array_values(array_unique(array_merge($muted, $blocked)));
    }

    private static function excerpt(?string $text, int $length = 120): ?string
    {
        if (empty($text)) {
            return null;
        }

        $clean = trim(preg_replace('/\s+/', ' ', strip_tags($text)));

        return mb_strlen($clean) > $length
            ? mb_substr($clean, 0, $length) . '…'
            : $clean;
    }
}
