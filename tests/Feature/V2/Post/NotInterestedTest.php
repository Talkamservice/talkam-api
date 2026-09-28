<?php

namespace Tests\Feature\V2\Post;

use App\Models\Post;
use App\Models\PostNotInterest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotInterestedTest extends TestCase
{
    use RefreshDatabase;

    public function test_toggle_creates_and_removes_not_interest_row(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/user/posts/not-interested", ["post_id" => $post->id])
            ->assertStatus(200)->assertJsonPath("data.not_interested", true);
        $this->assertSame(1, PostNotInterest::count());

        $this->postJson("/api/v2/user/posts/not-interested", ["post_id" => $post->id])
            ->assertJsonPath("data.not_interested", false);
        $this->assertSame(0, PostNotInterest::count());
    }

    public function test_marked_post_excluded_from_v2_feed(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();
        PostNotInterest::create(["user_id" => $user->id, "post_id" => $post->id]);
        Sanctum::actingAs($user);

        $ids = collect($this->getJson("/api/v2/user/posts?tab=latest")->json("data.data"))->pluck("id");
        $this->assertFalse($ids->contains($post->id));
    }

    public function test_marked_post_still_in_v1_feed(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();
        PostNotInterest::create(["user_id" => $user->id, "post_id" => $post->id]);
        Sanctum::actingAs($user);

        $ids = collect($this->getJson("/api/v1/user/posts?tab=latest")->json("data.data"))->pluck("id");
        $this->assertTrue($ids->contains($post->id));
    }

    public function test_requires_authentication(): void
    {
        $this->postJson("/api/v2/user/posts/not-interested", [])->assertStatus(401);
    }
}
