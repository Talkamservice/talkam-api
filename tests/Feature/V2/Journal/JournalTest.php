<?php

namespace Tests\Feature\V2\Journal;

use App\Models\Article;
use App\Models\NewsletterSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public TalkAM Journal (web §05): article reads + newsletter capture.
 */
class JournalTest extends TestCase
{
    use RefreshDatabase;

    private function article(array $overrides = []): Article
    {
        static $n = 0;
        $n++;

        return Article::create(array_merge([
            "slug" => "article-{$n}",
            "category" => "Self-Care",
            "tone" => "gold",
            "cover" => "linear-gradient(135deg,#DBB66E,#9A6E0A)",
            "title" => "Article {$n}",
            "excerpt" => "Excerpt {$n}",
            "author" => "Dr. Ngozi Eze",
            "author_initials" => "NE",
            "author_role" => "Clinical Psychologist",
            "author_bio" => "Bio {$n}",
            "read_time" => "7 min read",
            "display_date" => "Jul 12",
            "body" => [
                ["type" => "p", "text" => "Opening paragraph."],
                ["type" => "h2", "text" => "A heading"],
                ["type" => "list", "items" => ["one", "two"]],
            ],
            "sort_order" => $n,
            "published_at" => now(),
            "status" => Article::STATUS_PUBLISHED,
        ], $overrides));
    }

    /* ── Index ──────────────────────────────────────────────────────────── */

    public function test_index_returns_published_articles_in_order_without_body(): void
    {
        $this->article(["slug" => "first", "sort_order" => 0]);
        $this->article(["slug" => "second", "sort_order" => 1]);

        $response = $this->getJson("/api/v2/journal/articles");

        $response->assertOk();
        $data = $response->json("data");

        $this->assertCount(2, $data);
        $this->assertSame("first", $data[0]["slug"]);
        $this->assertSame("second", $data[1]["slug"]);
        // Card shape — camelCase, and no heavy body.
        $this->assertArrayHasKey("authorInitials", $data[0]);
        $this->assertArrayHasKey("readTime", $data[0]);
        $this->assertArrayNotHasKey("body", $data[0]);
    }

    public function test_index_hides_unpublished_articles(): void
    {
        $this->article(["slug" => "live"]);
        $this->article(["slug" => "draft", "status" => "draft"]);

        $slugs = collect($this->getJson("/api/v2/journal/articles")->json("data"))
            ->pluck("slug")
            ->all();

        $this->assertContains("live", $slugs);
        $this->assertNotContains("draft", $slugs);
    }

    /* ── Show ───────────────────────────────────────────────────────────── */

    public function test_show_returns_the_article_with_body_and_related(): void
    {
        $target = $this->article(["slug" => "sleep", "category" => "Self-Care"]);
        $sameCat = $this->article(["slug" => "mood", "category" => "Self-Care"]);
        $otherCat = $this->article(["slug" => "work", "category" => "Workplace Wellbeing"]);

        $response = $this->getJson("/api/v2/journal/articles/sleep");

        $response->assertOk();
        $article = $response->json("data.article");
        $related = $response->json("data.related");

        $this->assertSame("sleep", $article["slug"]);
        $this->assertArrayHasKey("body", $article);
        $this->assertSame("p", $article["body"][0]["type"]);
        $this->assertArrayHasKey("authorBio", $article);

        // Related excludes self, same-category first, max 3.
        $relatedSlugs = collect($related)->pluck("slug")->all();
        $this->assertNotContains("sleep", $relatedSlugs);
        $this->assertSame("mood", $relatedSlugs[0]); // same category ranks first
        $this->assertLessThanOrEqual(3, count($related));
    }

    public function test_show_related_is_capped_at_three(): void
    {
        $this->article(["slug" => "target"]);
        for ($i = 0; $i < 5; $i++) {
            $this->article(["slug" => "other-{$i}"]);
        }

        $related = $this->getJson("/api/v2/journal/articles/target")->json("data.related");

        $this->assertCount(3, $related);
    }

    public function test_unknown_slug_is_not_found(): void
    {
        $this->getJson("/api/v2/journal/articles/does-not-exist")->assertNotFound();
    }

    public function test_unpublished_article_is_not_found_by_slug(): void
    {
        $this->article(["slug" => "hidden", "status" => "draft"]);

        $this->getJson("/api/v2/journal/articles/hidden")->assertNotFound();
    }

    /* ── Newsletter ─────────────────────────────────────────────────────── */

    public function test_subscribe_accepts_a_valid_email(): void
    {
        $this->postJson("/api/v2/journal/subscribe", ["email" => "reader@example.com"])
            ->assertOk();

        $this->assertDatabaseHas("newsletter_subscribers", [
            "email" => "reader@example.com",
            "status" => NewsletterSubscriber::STATUS_SUBSCRIBED,
        ]);
    }

    public function test_subscribe_is_idempotent_on_repeat(): void
    {
        $this->postJson("/api/v2/journal/subscribe", ["email" => "Reader@Example.com"])->assertOk();
        $this->postJson("/api/v2/journal/subscribe", ["email" => "reader@example.com"])->assertOk();

        $this->assertSame(
            1,
            NewsletterSubscriber::where("email", "reader@example.com")->count()
        );
    }

    public function test_subscribe_rejects_a_malformed_email(): void
    {
        $this->postJson("/api/v2/journal/subscribe", ["email" => "not-an-email"])
            ->assertStatus(422);

        $this->assertDatabaseCount("newsletter_subscribers", 0);
    }
}
