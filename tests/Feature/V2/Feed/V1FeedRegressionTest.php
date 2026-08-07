<?php

namespace Tests\Feature\V2\Feed;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\TrendingTag;
use App\Models\User;
use App\Models\UserPostReaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pins v1 feed tab behavior before/while the v2 for_you branch exists.
 */
class V1FeedRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_v1_latest_tab_is_recency_ordered(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $older = Post::factory()->create(["created_at" => now()->subDays(2)]);
        $newer = Post::factory()->create(["created_at" => now()->subHour()]);
        // Engagement on the older post must not affect "latest" ordering.
        UserPostReaction::create([
            "user_id" => User::factory()->create()->id,
            "post_id" => $older->id,
            "action" => "Like",
        ]);

        $ids = collect($this->getJson("/api/v1/user/posts?tab=latest")->assertStatus(200)->json("data.data"))
            ->pluck("id");
        $this->assertTrue($ids->search($newer->id) < $ids->search($older->id));
    }

    public function test_v1_featured_tab_ranks_by_engagement_within_two_months(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $engaged = Post::factory()->create(["created_at" => now()->subWeek()]);
        $plain = Post::factory()->create(["created_at" => now()->subDay()]);
        $outside_window = Post::factory()->create(["created_at" => now()->subMonths(3)]);
        PostComment::create([
            "user_id" => User::factory()->create()->id,
            "post_id" => $engaged->id,
            "comment" => "test",
        ]);

        $ids = collect($this->getJson("/api/v1/user/posts?tab=featured")->assertStatus(200)->json("data.data"))
            ->pluck("id");
        $this->assertTrue($ids->search($engaged->id) < $ids->search($plain->id));
        $this->assertFalse($ids->contains($outside_window->id));
    }

    public function test_v1_trending_tab_still_matches_trending_tags(): void
    {
        Sanctum::actingAs(User::factory()->create());
        TrendingTag::factory()->create(["tag" => "healing", "count" => 50]);

        $tagged = Post::factory()->create(["tags" => "healing, hope"]);
        $untagged = Post::factory()->create();

        $ids = collect($this->getJson("/api/v1/user/posts?tab=trending")->assertStatus(200)->json("data.data"))
            ->pluck("id");
        $this->assertTrue($ids->contains($tagged->id));
        $this->assertFalse($ids->contains($untagged->id));
    }
}
