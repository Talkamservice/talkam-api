<?php

namespace Tests\Feature\V2\Post;

use App\Models\Post;
use App\Models\PostCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreatePostTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        $topic = PostCategory::factory()->interestTopic()->create();

        return array_merge([
            "category_id" => $topic->id,
            "type" => "Text",
            "title" => "Managing anxiety day to day",
            "body" => "Some thoughts on coping strategies.",
            "tags" => ["anxiety"],
        ], $overrides);
    }

    public function test_creates_post_with_valid_v2_payload(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v2/user/posts", $this->validPayload())
            ->assertStatus(200)
            ->assertJson(["success" => true]);

        $this->assertDatabaseHas("posts", ["title" => "Managing anxiety day to day"]);
    }

    public function test_rejects_missing_title(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $payload = $this->validPayload();
        unset($payload["title"]);

        $this->postJson("/api/v2/user/posts", $payload)
            ->assertStatus(422)->assertJson(["success" => false]);

        $this->assertSame(0, Post::count());
    }

    public function test_rejects_empty_tags(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v2/user/posts", $this->validPayload(["tags" => []]))
            ->assertStatus(422)->assertJson(["success" => false]);

        $this->assertSame(0, Post::count());
    }

    public function test_rejects_body_of_501_chars(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v2/user/posts", $this->validPayload(["body" => str_repeat("a", 501)]))
            ->assertStatus(422)->assertJson(["success" => false]);

        $this->assertSame(0, Post::count());
    }

    public function test_accepts_body_of_exactly_500_chars(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v2/user/posts", $this->validPayload(["body" => str_repeat("a", 500)]))
            ->assertStatus(200)->assertJson(["success" => true]);

        $this->assertSame(1, Post::count());
    }

    public function test_rejects_non_interest_topic_category_id(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $plain_category = PostCategory::factory()->create();

        $this->postJson("/api/v2/user/posts", $this->validPayload(["category_id" => $plain_category->id]))
            ->assertStatus(422)->assertJson(["success" => false]);

        $this->assertSame(0, Post::count());
    }

    public function test_persists_anonymous_flag(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v2/user/posts", $this->validPayload(["is_anonymous" => 1]))
            ->assertStatus(200);

        $this->assertDatabaseHas("posts", [
            "title" => "Managing anxiety day to day",
            "is_anonymous" => 1,
        ]);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson("/api/v2/user/posts", [])->assertStatus(401);
    }

    public function test_v1_store_still_accepts_nullable_title_tags_and_long_body(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $category = PostCategory::factory()->create();

        // v1 regression: no title, no tags, >500-char body — still accepted.
        $this->postJson("/api/v1/user/posts", [
            "category_id" => $category->id,
            "type" => "Text",
            "body" => str_repeat("b", 600),
        ])->assertStatus(200)->assertJson(["success" => true]);

        $this->assertSame(1, Post::count());
    }
}
