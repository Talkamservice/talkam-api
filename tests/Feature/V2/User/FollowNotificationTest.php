<?php

namespace Tests\Feature\V2\User;

use App\Models\PostCategory;
use App\Models\User;
use App\Models\UserFollow;
use App\Notifications\Post\NewFollowedUserPostNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FollowNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function publishPost(User $author, array $overrides = []): void
    {
        $topic = PostCategory::factory()->interestTopic()->create();
        Sanctum::actingAs($author);

        $this->postJson("/api/v2/user/posts", array_merge([
            "category_id" => $topic->id,
            "type" => "Text",
            "title" => "A new post",
            "body" => "Hello followers",
            "tags" => ["update"],
        ], $overrides))->assertStatus(200);
    }

    public function test_follower_notified_on_non_anonymous_post(): void
    {
        Notification::fake();
        $author = User::factory()->create();
        $follower = User::factory()->create();
        UserFollow::create(["follower_id" => $follower->id, "followed_id" => $author->id]);

        $this->publishPost($author);

        Notification::assertSentTo($follower, NewFollowedUserPostNotification::class);
    }

    public function test_no_notification_for_anonymous_post(): void
    {
        Notification::fake();
        $author = User::factory()->create();
        $follower = User::factory()->create();
        UserFollow::create(["follower_id" => $follower->id, "followed_id" => $author->id]);

        $this->publishPost($author, ["is_anonymous" => 1]);

        Notification::assertNotSentTo($follower, NewFollowedUserPostNotification::class);
    }

    public function test_no_notification_for_draft_post(): void
    {
        Notification::fake();
        $author = User::factory()->create();
        $follower = User::factory()->create();
        UserFollow::create(["follower_id" => $follower->id, "followed_id" => $author->id]);

        $this->publishPost($author, ["status" => "Drafted"]);

        Notification::assertNotSentTo($follower, NewFollowedUserPostNotification::class);
    }
}
