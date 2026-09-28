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

class TrendingFeedTest extends TestCase
{
    use RefreshDatabase;

    private function engage(Post $post, int $likes = 0, int $comments = 0): void
    {
        for ($i = 0; $i < $likes; $i++) {
            UserPostReaction::create([
                "user_id" => User::factory()->create()->id,
                "post_id" => $post->id,
                "action" => "Like",
            ]);
        }
        for ($i = 0; $i < $comments; $i++) {
            PostComment::create([
                "user_id" => User::factory()->create()->id,
                "post_id" => $post->id,
                "comment" => "test comment",
            ]);
        }
    }

    public function test_trending_ranks_by_engagement(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $low = Post::factory()->create(["created_at" => now()->subDay()]);
        $high = Post::factory()->create(["created_at" => now()->subDays(3)]);
        $this->engage($high, likes: 3, comments: 2);
        $this->engage($low, likes: 1);

        $ids = collect($this->getJson("/api/v2/user/posts?tab=trending")->json("data.data"))->pluck("id");
        $this->assertTrue($ids->search($high->id) < $ids->search($low->id));
    }

    public function test_trending_excludes_posts_outside_recent_window(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $old = Post::factory()->create(["created_at" => now()->subMonths(3)]);
        $this->engage($old, likes: 5, comments: 5);
        $recent = Post::factory()->create(["created_at" => now()->subDay()]);

        $ids = collect($this->getJson("/api/v2/user/posts?tab=trending")->json("data.data"))->pluck("id");
        $this->assertFalse($ids->contains($old->id));
        $this->assertTrue($ids->contains($recent->id));
    }

    public function test_trending_ignores_tag_matching(): void
    {
        Sanctum::actingAs(User::factory()->create());
        TrendingTag::factory()->create(["tag" => "anxiety", "count" => 99]);

        $tagged_unengaged = Post::factory()->create([
            "tags" => "anxiety",
            "created_at" => now()->subDay(),
        ]);
        $engaged = Post::factory()->create(["created_at" => now()->subDay()]);
        $this->engage($engaged, likes: 3);

        $ids = collect($this->getJson("/api/v2/user/posts?tab=trending")->json("data.data"))->pluck("id");
        $this->assertTrue($ids->search($engaged->id) < $ids->search($tagged_unengaged->id));
    }
}
