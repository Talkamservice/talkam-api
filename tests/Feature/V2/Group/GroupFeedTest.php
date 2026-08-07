<?php

namespace Tests\Feature\V2\Group;

use App\Models\Group;
use App\Models\Post;
use App\Models\User;
use App\Models\UserPostReaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GroupFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_feed_returns_only_group_posts(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $group = Group::factory()->create();
        $group_post = Post::factory()->create(["group_id" => $group->id]);
        $other_post = Post::factory()->create();

        $ids = collect($this->getJson("/api/v2/user/posts?group_id={$group->id}")->json("data.data"))
            ->pluck("id");
        $this->assertTrue($ids->contains($group_post->id));
        $this->assertFalse($ids->contains($other_post->id));
    }

    public function test_trending_tab_orders_by_engagement(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $group = Group::factory()->create();
        $plain = Post::factory()->create(["group_id" => $group->id, "created_at" => now()->subDay()]);
        $engaged = Post::factory()->create(["group_id" => $group->id, "created_at" => now()->subDays(2)]);
        foreach (range(1, 3) as $i) {
            UserPostReaction::create([
                "user_id" => User::factory()->create()->id,
                "post_id" => $engaged->id,
                "action" => "Like",
            ]);
        }

        $ids = collect(
            $this->getJson("/api/v2/user/posts?group_id={$group->id}&tab=trending")->json("data.data")
        )->pluck("id");
        $this->assertTrue($ids->search($engaged->id) < $ids->search($plain->id));
    }

    public function test_group_posts_excluded_from_main_feed(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $group = Group::factory()->closed()->create();
        $group_post = Post::factory()->create(["group_id" => $group->id]);
        Post::factory()->create();

        $ids = collect($this->getJson("/api/v2/user/posts?tab=latest")->json("data.data"))->pluck("id");
        $this->assertFalse($ids->contains($group_post->id));
    }
}
