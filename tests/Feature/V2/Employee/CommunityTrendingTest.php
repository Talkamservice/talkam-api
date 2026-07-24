<?php

namespace Tests\Feature\V2\Employee;

use App\Constants\General\StatusConstants;
use App\Constants\Post\PostCategoryConstants;
use App\Models\BlockedUser;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\User;
use App\Models\UserMute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommunityTrendingTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    private function topic(string $name): PostCategory
    {
        return PostCategory::create([
            "name" => $name,
            "status" => StatusConstants::ACTIVE,
            "type" => PostCategoryConstants::TYPE_INTEREST_TOPIC,
        ]);
    }

    private function makePost(PostCategory $topic, User $author, string $body, $created = null): Post
    {
        return Post::factory()->create([
            "category_id" => $topic->id,
            "user_id" => $author->id,
            "body" => $body,
            "status" => StatusConstants::ACTIVE,
            "created_at" => $created ?? now(),
        ]);
    }

    public function test_topics_are_ranked_by_post_count_with_a_snippet(): void
    {
        $this->actor();
        $author = User::factory()->create();

        $work = $this->topic("Work Stress");
        $anxiety = $this->topic("Anxiety");

        $this->makePost($work, $author, "Anyone else feel like Mondays never end lately?", now()->subHours(2));
        $this->makePost($work, $author, "Second work post");
        $this->makePost($work, $author, "Third work post");
        $this->makePost($anxiety, $author, "Small win today — I spoke up in the meeting.");

        $topics = $this->getJson("/api/v2/user/community/trending")
            ->assertStatus(200)
            ->json("data.topics");

        $this->assertCount(2, $topics);
        $this->assertSame("Work Stress", $topics[0]["name"]);
        $this->assertSame(3, $topics[0]["posts"]);
        $this->assertSame("Anxiety", $topics[1]["name"]);
        $this->assertSame(1, $topics[1]["posts"]);
        $this->assertSame("Small win today — I spoke up in the meeting.", $topics[1]["snippet"]);
        $this->assertNotEmpty($topics[1]["time_ago"]);
    }

    /** Anonymity is the whole point of this surface. */
    public function test_no_author_identity_appears_in_the_payload(): void
    {
        $this->actor();
        $author = User::factory()->create([
            "username" => "identifiable_person",
            "first_name" => "Chidinma",
            "last_name" => "Eze",
        ]);

        $this->makePost($this->topic("Anxiety"), $author, "A post body");

        $response = $this->getJson("/api/v2/user/community/trending")->assertStatus(200);
        $body = $response->getContent();

        foreach (["identifiable_person", "Chidinma", "Eze", $author->email] as $needle) {
            $this->assertStringNotContainsString($needle, $body);
        }

        foreach ($response->json("data.topics") as $topic) {
            foreach (["user_id", "username", "author", "avatar", "user"] as $key) {
                $this->assertArrayNotHasKey($key, $topic);
            }
        }
    }

    public function test_muted_authors_are_excluded(): void
    {
        $user = $this->actor();
        $muted = User::factory()->create();
        $visible = User::factory()->create();

        UserMute::create(["user_id" => $user->id, "muted_user_id" => $muted->id]);

        $topic = $this->topic("Anxiety");
        $this->makePost($topic, $muted, "Muted post");
        $this->makePost($topic, $visible, "Visible post");

        $topics = $this->getJson("/api/v2/user/community/trending")->json("data.topics");

        $this->assertSame(1, $topics[0]["posts"]);
        $this->assertSame("Visible post", $topics[0]["snippet"]);
    }

    public function test_blocked_authors_are_excluded(): void
    {
        $user = $this->actor();
        $blocked = User::factory()->create();

        BlockedUser::create([
            "blocker_id" => $user->id,
            "blocked_user_id" => $blocked->id,
            "status" => StatusConstants::ACTIVE,
        ]);

        $this->makePost($this->topic("Anxiety"), $blocked, "Blocked post");

        $this->assertSame([], $this->getJson("/api/v2/user/community/trending")->json("data.topics"));
    }

    public function test_posts_outside_the_window_are_excluded(): void
    {
        $this->actor();
        $author = User::factory()->create();

        $topic = $this->topic("Anxiety");
        $this->makePost($topic, $author, "Recent", now()->subDays(2));
        $this->makePost($topic, $author, "Old", now()->subDays(30));

        $topics = $this->getJson("/api/v2/user/community/trending?days=7")->json("data.topics");

        $this->assertSame(1, $topics[0]["posts"]);
    }

    public function test_inactive_posts_are_excluded(): void
    {
        $this->actor();
        $author = User::factory()->create();

        $topic = $this->topic("Anxiety");
        $this->makePost($topic, $author, "Live post");
        Post::factory()->create([
            "category_id" => $topic->id,
            "user_id" => $author->id,
            "body" => "Drafted post",
            "status" => StatusConstants::DRAFTED,
        ]);

        $topics = $this->getJson("/api/v2/user/community/trending")->json("data.topics");

        $this->assertSame(1, $topics[0]["posts"]);
    }

    public function test_only_interest_topic_categories_are_rolled_up(): void
    {
        $this->actor();
        $author = User::factory()->create();

        $ordinary = PostCategory::create(["name" => "Not A Topic", "status" => StatusConstants::ACTIVE]);
        $this->makePost($ordinary, $author, "Off-taxonomy post");

        $this->assertSame([], $this->getJson("/api/v2/user/community/trending")->json("data.topics"));
    }

    public function test_long_bodies_are_truncated_to_an_excerpt(): void
    {
        $this->actor();
        $author = User::factory()->create();

        $this->makePost($this->topic("Anxiety"), $author, str_repeat("word ", 100));

        $snippet = $this->getJson("/api/v2/user/community/trending")->json("data.topics.0.snippet");

        $this->assertLessThanOrEqual(121, mb_strlen($snippet));
        $this->assertStringEndsWith("…", $snippet);
    }

    public function test_an_empty_community_returns_an_empty_list(): void
    {
        $this->actor();

        $this->getJson("/api/v2/user/community/trending")
            ->assertStatus(200)
            ->assertJsonPath("data.topics", []);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson("/api/v2/user/community/trending")->assertStatus(401);
    }
}
