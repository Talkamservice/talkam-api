<?php

namespace Database\Seeders;

use App\Models\Article;
use Illuminate\Database\Seeder;

/**
 * Seeds the TalkAM Journal (web §05) so the public blog is served by the API.
 *
 * Content lives in `database/data/journal_articles.json`, transcribed verbatim
 * from "TalkAM Blog.dc.html" (the web mock the design was signed off against).
 * Idempotent — keyed on slug, safe to re-run; editing the JSON is a data
 * change, not a deploy.
 */
class JournalSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path("data/journal_articles.json");

        if (!is_file($path)) {
            return;
        }

        $articles = json_decode(file_get_contents($path), true) ?: [];

        foreach ($articles as $article) {
            Article::updateOrCreate(
                ["slug" => $article["slug"]],
                [
                    "category" => $article["category"],
                    "tone" => $article["tone"] ?? "blue",
                    "cover" => $article["cover"] ?? null,
                    "title" => $article["title"],
                    "excerpt" => $article["excerpt"] ?? null,
                    "author" => $article["author"],
                    "author_initials" => $article["author_initials"] ?? null,
                    "author_role" => $article["author_role"] ?? null,
                    "author_bio" => $article["author_bio"] ?? null,
                    "read_time" => $article["read_time"] ?? null,
                    "display_date" => $article["display_date"] ?? null,
                    "body" => $article["body"] ?? [],
                    "sort_order" => $article["sort_order"] ?? 0,
                    "published_at" => $article["published_at"] ?? now(),
                    "status" => Article::STATUS_PUBLISHED,
                ]
            );
        }
    }
}
