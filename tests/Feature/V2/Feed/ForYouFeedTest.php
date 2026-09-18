<?php

namespace Tests\Feature\V2\Feed;

use App\Models\Post;
use App\Models\PostCategory;
use App\Models\User;
use App\Models\UserInterest;
use App\Models\UserPostReaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ForYouFeedTest extends TestCase
{
    use RefreshDatabase;

    private function userWithInterest(): array
    {
        $user = User::factory()->create();
        $interest_category = PostCategory::factory()->interestTopic()->create();
        UserInterest::create(["user_id" => $user->id, "category_id" => $interest_category->id]);

        return [$user, $interest_category];
    }

    public function test_for_you_returns_envelope_with_paginated_posts(): void
    {
        [$user, $category] = $this->userWithInterest();
        Sanctum::actingAs($user);
        Post::factory()->count(2)->create(["category_id" => $category->id]);

        $this->getJson("/api/v2/user/posts?tab=for_you")
            ->assertStatus(200)
            ->assertJson(["success" => true, "code" => 200])
            ->assertJsonStructure(["message", "data" => ["data", "pagination_meta" => ["total"]], "success", "code"]);
    }

    public function test_interest_posts_rank_before_non_interest_posts(): void
    {
        [$user, $interest_category] = $this->userWithInterest();
        Sanctum::actingAs($user);
        $other_category = PostCategory::factory()->create();

        // The non-interest post is newer AND more engaged — interest must still win.
        $interest_post = Post::factory()->create([
            "category_id" => $interest_category->id,
            "created_at" => now()->subDay(),
        ]);
        $other_post = Post::factory()->create([
            "category_id" => $other_category->id,
            "created_at" => now(),
        ]);
        UserPostReaction::create(["user_id" => $user->id, "post_id" => $other_post->id, "action" => "Like"]);

        $ids = collect($this->getJson("/api/v2/user/posts?tab=for_you")->json("data.data"))->pluck("id");
        $this->assertTrue(
            $ids->search($interest_post->id) < $ids->search($other_post->id),
            "Interest post should rank before non-interest post"
        );
    }

    public function test_interest_posts_are_recency_ordered(): void
    {
        [$user, $category] = $this->userWithInterest();
        Sanctum::actingAs($user);

        $older = Post::factory()->create(["category_id" => $category->id, "created_at" => now()->subDays(2)]);
        $newer = Post::factory()->create(["category_id" => $category->id, "created_at" => now()->subHour()]);

        $ids = collect($this->getJson("/api/v2/user/posts?tab=for_you")->json("data.data"))->pluck("id");
        $this->assertTrue($ids->search($newer->id) < $ids->search($older->id));
    }

    public function test_backfill_fills_feed_when_interests_have_no_posts(): void
    {
        [$user] = $this->userWithInterest();
        Sanctum::actingAs($user);
        $other_category = PostCategory::factory()->create();
        Post::factory()->count(3)->create(["category_id" => $other_category->id]);

        $data = $this->getJson("/api/v2/user/posts?tab=for_you")->assertStatus(200)->json("data.data");
        $this->assertNotEmpty($data);
    }

    public function test_user_without_interests_gets_backfill_feed(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Post::factory()->count(3)->create();

        $data = $this->getJson("/api/v2/user/posts?tab=for_you")->assertStatus(200)->json("data.data");
        $this->assertNotEmpty($data);
    }

    public function test_category_id_filter_narrows_to_topic(): void
    {
        [$user, $category] = $this->userWithInterest();
        Sanctum::actingAs($user);
        $other = PostCategory::factory()->create();
        $in_topic = Post::factory()->create(["category_id" => $category->id]);
        Post::factory()->create(["category_id" => $other->id]);

        $ids = collect(
            $this->getJson("/api/v2/user/posts?tab=for_you&category_id={$category->id}")->json("data.data")
        )->pluck("id");

        $this->assertEquals([$in_topic->id], $ids->all());
    }

    public function test_feed_fetch_logs_impressions(): void
    {
        [$user, $category] = $this->userWithInterest();
        Sanctum::actingAs($user);
        $post = Post::factory()->create(["category_id" => $category->id]);

        $this->getJson("/api/v2/user/posts?tab=for_you")->assertStatus(200);

        $this->assertDatabaseHas("post_stats", ["post_id" => $post->id]);
    }

    public function test_feed_requires_authentication(): void
    {
        $this->getJson("/api/v2/user/posts?tab=for_you")->assertStatus(401);
    }
}
