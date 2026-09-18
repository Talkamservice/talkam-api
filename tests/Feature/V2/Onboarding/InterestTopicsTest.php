<?php

namespace Tests\Feature\V2\Onboarding;

use App\Models\PostCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InterestTopicsTest extends TestCase
{
    use RefreshDatabase;

    private function seedTopics(): array
    {
        $parent = PostCategory::factory()->create(["name" => "Mental Health"]);
        $topics = PostCategory::factory()->count(3)->interestTopic()->create([
            "category_id" => $parent->id,
        ]);
        $unrelated = PostCategory::factory()->create(["name" => "General Wellness"]);

        return [$parent, $topics, $unrelated];
    }

    public function test_topics_endpoint_returns_only_type_marked_rows(): void
    {
        [$parent, $topics, $unrelated] = $this->seedTopics();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson("/api/v2/user/interest-topics")
            ->assertStatus(200)
            ->assertJson(["success" => true, "code" => 200])
            ->assertJsonCount(3, "data");

        $names = collect($response->json("data"))->pluck("name");
        foreach ($topics as $topic) {
            $this->assertTrue($names->contains($topic->name));
        }
        $this->assertFalse($names->contains($unrelated->name));
    }

    public function test_parent_category_is_excluded_from_topics(): void
    {
        [$parent] = $this->seedTopics();
        Sanctum::actingAs(User::factory()->create());

        $names = collect($this->getJson("/api/v2/user/interest-topics")->json("data"))->pluck("name");
        $this->assertFalse($names->contains($parent->name));
    }

    public function test_topics_are_selected_by_marker_not_id(): void
    {
        $parent = PostCategory::factory()->create(["name" => "Mental Health"]);
        // Non-contiguous, arbitrary IDs — the marker must find them all.
        foreach ([77, 203, 999] as $id) {
            PostCategory::factory()->interestTopic()->create([
                "id" => $id,
                "category_id" => $parent->id,
            ]);
        }
        Sanctum::actingAs(User::factory()->create());

        $ids = collect($this->getJson("/api/v2/user/interest-topics")->json("data"))->pluck("id");
        $this->assertEqualsCanonicalizing([77, 203, 999], $ids->all());
    }

    public function test_topics_require_authentication(): void
    {
        $this->getJson("/api/v2/user/interest-topics")->assertStatus(401);
    }
}
