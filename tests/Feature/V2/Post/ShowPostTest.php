<?php

namespace Tests\Feature\V2\Post;

use App\Models\Post;
use App\Models\User;
use App\Models\UserMute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShowPostTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_post_detail_in_standard_envelope(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $post = Post::factory()->create(["views_count" => 42]);

        $this->getJson("/api/v2/user/posts/{$post->id}")
            ->assertStatus(200)
            ->assertJson(["success" => true, "code" => 200])
            ->assertJsonPath("data.id", $post->id);
    }

    public function test_returns_muted_authors_post_when_fetched_directly(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $author = User::factory()->create();
        $post = Post::factory()->create(["user_id" => $author->id]);
        UserMute::create(["user_id" => $user->id, "muted_user_id" => $author->id]);

        // Mute is feeds-only — direct fetch still works.
        $this->getJson("/api/v2/user/posts/{$post->id}")
            ->assertStatus(200)
            ->assertJsonPath("data.id", $post->id);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson("/api/v2/user/posts/1")->assertStatus(401);
    }
}
