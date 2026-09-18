<?php

namespace Tests\Feature\V2\Onboarding;

use App\Models\PostCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pins v1 category/interest behavior around the interest-topic seeding
 * strategy (planning doc 02 §3a).
 */
class V1CategoryRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_v1_category_list_hides_interest_topic_children(): void
    {
        $parent = PostCategory::factory()->create(["name" => "Mental Health"]);
        $topics = PostCategory::factory()->count(3)->interestTopic()->create([
            "category_id" => $parent->id,
        ]);
        Sanctum::actingAs(User::factory()->create());

        $names = collect(
            $this->getJson("/api/v1/user/post-categories")->assertStatus(200)->json("data")
        )->pluck("name");

        $this->assertTrue($names->contains("Mental Health"));
        foreach ($topics as $topic) {
            $this->assertFalse($names->contains($topic->name));
        }
    }

    public function test_v1_single_interest_toggle_still_works_without_minimum(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $category = PostCategory::factory()->create();

        $this->postJson("/api/v1/user/profile/interests/add-remove", [
            "category_id" => $category->id,
        ])->assertStatus(200)->assertJson(["success" => true]);

        $this->assertDatabaseHas("user_interests", [
            "user_id" => $user->id,
            "category_id" => $category->id,
        ]);
    }
}
