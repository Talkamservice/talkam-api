<?php

namespace Tests\Feature\V2\Post;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use App\Notifications\Comment\NewCommentTagMentionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreateCommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_comment_of_501_chars(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $post = Post::factory()->create();

        $this->postJson("/api/v2/user/post-comments", [
            "post_id" => $post->id,
            "comment" => str_repeat("a", 501),
        ])->assertStatus(422)->assertJson(["success" => false]);

        $this->assertSame(0, PostComment::count());
    }

    public function test_accepts_comment_of_exactly_500_chars(): void
    {
        Notification::fake();
        Sanctum::actingAs(User::factory()->create());
        $post = Post::factory()->create();

        $this->postJson("/api/v2/user/post-comments", [
            "post_id" => $post->id,
            "comment" => str_repeat("a", 500),
        ])->assertStatus(200)->assertJson(["success" => true]);

        $this->assertSame(1, PostComment::count());
    }

    public function test_persists_per_reply_anonymous_flag(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $post = Post::factory()->create();

        $this->postJson("/api/v2/user/post-comments", [
            "post_id" => $post->id,
            "comment" => "posting this anonymously",
            "is_anonymous" => 1,
        ])->assertStatus(200);

        $this->assertDatabaseHas("post_comments", [
            "user_id" => $user->id,
            "is_anonymous" => 1,
        ]);
    }

    public function test_threaded_reply_links_to_parent(): void
    {
        Notification::fake();
        Sanctum::actingAs(User::factory()->create());
        $post = Post::factory()->create();
        $parent = PostComment::create([
            "post_id" => $post->id,
            "user_id" => User::factory()->create()->id,
            "comment" => "parent comment",
        ]);

        $this->postJson("/api/v2/user/post-comments", [
            "post_id" => $post->id,
            "parent_id" => $parent->id,
            "reply_comment_id" => $parent->id,
            "comment" => "replying to you",
        ])->assertStatus(200);

        $this->assertDatabaseHas("post_comments", [
            "parent_id" => $parent->id,
            "reply_comment_id" => $parent->id,
            "comment" => "replying to you",
        ]);
    }

    public function test_mention_creates_mention_notification(): void
    {
        Notification::fake();
        Sanctum::actingAs(User::factory()->create());
        $mentioned = User::factory()->create(["username" => "drossy"]);
        $post = Post::factory()->create();

        // v1 mention syntax: $@username$ (see findSpecialWords).
        $this->postJson("/api/v2/user/post-comments", [
            "post_id" => $post->id,
            "comment" => 'Hey $@drossy$ what do you think?',
        ])->assertStatus(200);

        Notification::assertSentTo($mentioned, NewCommentTagMentionNotification::class);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson("/api/v2/user/post-comments", [])->assertStatus(401);
    }
}
