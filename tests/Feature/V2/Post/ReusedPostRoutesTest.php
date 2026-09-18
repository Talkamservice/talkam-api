<?php

namespace Tests\Feature\V2\Post;

use App\Models\Guideline;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\PostComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReusedPostRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_reaction_toggles_via_v2_route(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $post = Post::factory()->create();

        $this->postJson("/api/v2/user/posts/reaction", [
            "post_id" => $post->id,
            "action" => "Like",
        ])->assertStatus(200);
        $this->assertDatabaseHas("user_post_reactions", ["post_id" => $post->id, "user_id" => $user->id]);

        $this->postJson("/api/v2/user/posts/reaction", [
            "post_id" => $post->id,
            "action" => "Like",
        ])->assertStatus(200);
        $this->assertDatabaseMissing("user_post_reactions", ["post_id" => $post->id, "user_id" => $user->id]);
    }

    public function test_comment_reaction_toggles_via_v2_route(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $post = Post::factory()->create();
        $comment = PostComment::create([
            "post_id" => $post->id,
            "user_id" => User::factory()->create()->id,
            "comment" => "nice one",
        ]);

        $this->postJson("/api/v2/user/post-comments/reaction", [
            "comment_id" => $comment->id,
            "action" => "Like",
        ])->assertStatus(200);

        $this->assertDatabaseHas("user_comment_reactions", [
            "comment_id" => $comment->id,
            "user_id" => $user->id,
        ]);
    }

    public function test_report_post_creates_report_row(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $post = Post::factory()->create();

        $this->postJson("/api/v2/user/posts/report", [
            "post_id" => $post->id,
            "reason" => "Spam content",
        ])->assertStatus(200);

        $this->assertDatabaseHas("post_reports", ["post_id" => $post->id]);
    }

    public function test_report_comment_creates_report_row(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $post = Post::factory()->create();
        $comment = PostComment::create([
            "post_id" => $post->id,
            "user_id" => User::factory()->create()->id,
            "comment" => "reported comment",
        ]);

        $this->postJson("/api/v2/user/posts/report-comment", [
            "comment_id" => $comment->id,
            "post_id" => $post->id,
            "reason" => "Harassment",
        ])->assertStatus(200);

        $this->assertDatabaseHas("comment_reports", ["comment_id" => $comment->id]);
    }

    public function test_block_user_via_v2_route(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/user/blocked-users/add", [
            "blocked_user_id" => $target->id,
        ])->assertStatus(200);

        $this->assertDatabaseHas("blocked_users", [
            "blocker_id" => $user->id,
            "blocked_user_id" => $target->id,
        ]);
    }

    public function test_save_as_draft_creates_drafted_post(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $category = PostCategory::factory()->interestTopic()->create();

        $this->postJson("/api/v2/user/post-drafts", [
            "category_id" => $category->id,
            "type" => "Text",
            "title" => "Draft title",
            "body" => "Draft body",
        ])->assertStatus(200);

        $this->assertDatabaseHas("posts", ["title" => "Draft title", "status" => "Drafted"]);
    }

    public function test_schedule_persists_publish_at(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $category = PostCategory::factory()->interestTopic()->create();
        $publish_at = now()->addDay()->format("Y-m-d H:i:s");

        $this->postJson("/api/v2/user/posts", [
            "category_id" => $category->id,
            "type" => "Text",
            "title" => "Scheduled title",
            "body" => "Scheduled body",
            "tags" => ["future"],
            "status" => "Scheduled",
            "publish_at" => $publish_at,
        ])->assertStatus(200);

        $this->assertDatabaseHas("posts", [
            "title" => "Scheduled title",
            "status" => "Scheduled",
        ]);
    }

    public function test_guidelines_returns_rules_list(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Guideline::create(["title" => "Respect one another", "status" => "Active"]);

        $response = $this->getJson("/api/v2/user/guidelines")->assertStatus(200)
            ->assertJson(["success" => true]);

        $titles = collect($response->json("data"))->pluck("title");
        $this->assertTrue($titles->contains("Respect one another"));
    }

    public function test_auth_gated_routes_return_401(): void
    {
        $this->postJson("/api/v2/user/posts/report", [])->assertStatus(401);
        $this->postJson("/api/v2/user/posts/report-comment", [])->assertStatus(401);
        $this->postJson("/api/v2/user/blocked-users/add", [])->assertStatus(401);
        $this->postJson("/api/v2/user/post-drafts", [])->assertStatus(401);
        $this->getJson("/api/v2/user/guidelines")->assertStatus(401);
        $this->postJson("/api/v2/user/post-comments/reaction", [])->assertStatus(401);
    }
}
