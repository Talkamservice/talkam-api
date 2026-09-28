<?php

namespace Tests\Feature\V2\Post;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * GET /users/{id}/posts and /users/{id}/comments — thin path-addressed
 * wrappers around PostController@index / PostCommentController@index's
 * existing ?user_id= filter, so the same visibility rules apply.
 */
class UserContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_only_the_target_users_posts(): void
    {
        $target = User::factory()->create();
        $other = User::factory()->create();
        $mine = Post::factory()->create(["user_id" => $target->id]);
        Post::factory()->create(["user_id" => $other->id]);

        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson("/api/v2/users/{$target->id}/posts")->assertStatus(200);

        $ids = collect($response->json("data.data"))->pluck("id")->all();
        $this->assertSame([$mine->id], $ids);
    }

    public function test_anonymous_posts_hidden_from_non_authors(): void
    {
        $target = User::factory()->create();
        Post::factory()->create(["user_id" => $target->id, "is_anonymous" => 1]);

        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson("/api/v2/users/{$target->id}/posts")->assertStatus(200);

        $this->assertEmpty($response->json("data.data"));
    }

    public function test_anonymous_posts_visible_to_their_own_author(): void
    {
        $target = User::factory()->create();
        $post = Post::factory()->create(["user_id" => $target->id, "is_anonymous" => 1]);

        Sanctum::actingAs($target);

        $response = $this->getJson("/api/v2/users/{$target->id}/posts")->assertStatus(200);

        $ids = collect($response->json("data.data"))->pluck("id")->all();
        $this->assertSame([$post->id], $ids);
    }

    public function test_returns_only_the_target_users_comments(): void
    {
        $target = User::factory()->create();
        $other = User::factory()->create();
        $post = Post::factory()->create();
        $mine = PostComment::create(["user_id" => $target->id, "post_id" => $post->id, "comment" => "hi"]);
        PostComment::create(["user_id" => $other->id, "post_id" => $post->id, "comment" => "hey"]);

        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson("/api/v2/users/{$target->id}/comments")->assertStatus(200);

        $ids = collect($response->json("data"))->pluck("id")->all();
        $this->assertSame([$mine->id], $ids);
    }

    public function test_requires_authentication(): void
    {
        $target = User::factory()->create();

        $this->getJson("/api/v2/users/{$target->id}/posts")->assertStatus(401);
        $this->getJson("/api/v2/users/{$target->id}/comments")->assertStatus(401);
    }
}
