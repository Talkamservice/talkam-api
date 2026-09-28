<?php

namespace Tests\Feature\V2\User;

use App\Models\BlockedUser;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use App\Models\UserMute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MuteToggleTest extends TestCase
{
    use RefreshDatabase;

    public function test_toggle_creates_and_removes_mute_row(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/user/mutes/toggle", ["user_id" => $target->id])
            ->assertStatus(200)->assertJsonPath("data.muted", true);
        $this->assertSame(1, UserMute::count());

        $this->postJson("/api/v2/user/mutes/toggle", ["user_id" => $target->id])
            ->assertJsonPath("data.muted", false);
        $this->assertSame(0, UserMute::count());
    }

    public function test_muted_users_posts_excluded_from_v2_feed(): void
    {
        $user = User::factory()->create();
        $muted_author = User::factory()->create();
        $post = Post::factory()->create(["user_id" => $muted_author->id]);
        UserMute::create(["user_id" => $user->id, "muted_user_id" => $muted_author->id]);
        Sanctum::actingAs($user);

        $ids = collect($this->getJson("/api/v2/user/posts?tab=latest")->json("data.data"))->pluck("id");
        $this->assertFalse($ids->contains($post->id));
    }

    public function test_muted_users_comments_excluded_from_v2_listing(): void
    {
        $user = User::factory()->create();
        $muted_author = User::factory()->create();
        $post = Post::factory()->create();
        $comment = PostComment::create([
            "post_id" => $post->id,
            "user_id" => $muted_author->id,
            "comment" => "you won't see this",
        ]);
        UserMute::create(["user_id" => $user->id, "muted_user_id" => $muted_author->id]);
        Sanctum::actingAs($user);

        $ids = collect($this->getJson("/api/v2/user/post-comments?post_id={$post->id}")->json("data"))
            ->pluck("id");
        $this->assertFalse($ids->contains($comment->id));
    }

    public function test_muted_users_posts_still_in_v1_feed(): void
    {
        $user = User::factory()->create();
        $muted_author = User::factory()->create();
        $post = Post::factory()->create(["user_id" => $muted_author->id]);
        UserMute::create(["user_id" => $user->id, "muted_user_id" => $muted_author->id]);
        Sanctum::actingAs($user);

        $ids = collect($this->getJson("/api/v1/user/posts?tab=latest")->json("data.data"))->pluck("id");
        $this->assertTrue($ids->contains($post->id));
    }

    public function test_mute_creates_no_block_record(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/user/mutes/toggle", ["user_id" => $target->id])->assertStatus(200);

        $this->assertSame(0, BlockedUser::count());
    }

    public function test_requires_authentication(): void
    {
        $this->postJson("/api/v2/user/mutes/toggle", [])->assertStatus(401);
        $this->getJson("/api/v2/user/mutes")->assertStatus(401);
    }
}
