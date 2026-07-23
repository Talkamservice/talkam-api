<?php

namespace Tests\Feature\V2\Feed;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PostEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_detail_returns_envelope(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $post = Post::factory()->create();

        $this->getJson("/api/v2/user/posts/{$post->id}")
            ->assertStatus(200)
            ->assertJson(["success" => true, "code" => 200])
            ->assertJsonPath("data.id", $post->id);
    }

    public function test_post_detail_errors_for_unknown_post(): void
    {
        Sanctum::actingAs(User::factory()->create());

        // v1 show maps ModelNotFoundException to a 400 error envelope (reused as-is).
        $this->getJson("/api/v2/user/posts/999999")
            ->assertStatus(400)
            ->assertJson(["success" => false]);
    }

    public function test_reaction_creates_like_row_and_increments_count(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $post = Post::factory()->create();

        $this->postJson("/api/v2/user/posts/reaction", [
            "post_id" => $post->id,
            "action" => "Like",
        ])->assertStatus(200)->assertJson(["success" => true]);

        $this->assertDatabaseHas("user_post_reactions", [
            "post_id" => $post->id,
            "user_id" => $user->id,
            "action" => "Like",
        ]);
    }

    public function test_stats_save_records_share_and_fetch_returns_it(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $post = Post::factory()->create();

        $this->postJson("/api/v2/user/posts/stats/save", [
            "post_id" => $post->id,
            "shares" => true,
        ])->assertStatus(200)->assertJson(["success" => true]);

        $this->assertDatabaseHas("post_stats", ["post_id" => $post->id]);

        $this->getJson("/api/v2/user/posts/stats/fetch?post_id={$post->id}")
            ->assertStatus(200)
            ->assertJson(["success" => true]);
    }

    public function test_post_endpoints_require_authentication(): void
    {
        $this->getJson("/api/v2/user/posts/1")->assertStatus(401);
        $this->postJson("/api/v2/user/posts/reaction", [])->assertStatus(401);
        $this->postJson("/api/v2/user/posts/stats/save", [])->assertStatus(401);
        $this->getJson("/api/v2/user/posts/stats/fetch")->assertStatus(401);
    }
}
