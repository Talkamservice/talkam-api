<?php

namespace App\Services\Journal;

use App\Models\Article;
use App\Models\NewsletterSubscriber;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * The public TalkAM Journal: article reads and newsletter capture (web §05).
 * No auth, no tenant scoping — editorial marketing content.
 */
class JournalService
{
    /** The ordered, published article list (card fields; no body). */
    public static function articles()
    {
        return Article::published()
            ->orderBy("sort_order")
            ->orderByDesc("published_at")
            ->get();
    }

    /**
     * Related = same category first, then fill from the rest, max 3 — the
     * mock's `relatedArticles` rule, computed server-side.
     */
    public static function related(Article $article, int $limit = 3)
    {
        $others = Article::published()
            ->where("id", "!=", $article->id)
            ->orderBy("sort_order")
            ->get();

        return $others
            ->sortByDesc(fn ($a) => $a->category === $article->category ? 1 : 0)
            ->values()
            ->take($limit);
    }

    /**
     * Idempotent newsletter capture. A repeat email is a no-op success — no
     * enumeration signal, no leak of prior state.
     */
    public static function subscribe(array $data): NewsletterSubscriber
    {
        $validator = Validator::make($data, [
            "email" => "required|email|max:191",
            "source" => "nullable|string|max:60",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        return NewsletterSubscriber::firstOrCreate(
            ["email" => strtolower(trim($validated["email"]))],
            [
                "source" => $validated["source"] ?? "journal",
                "status" => NewsletterSubscriber::STATUS_SUBSCRIBED,
            ]
        );
    }
}
