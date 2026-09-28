<?php

namespace Tests\Feature\V2\Onboarding;

use App\Models\PostCategory;
use App\Models\User;
use App\Models\UserInterest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InterestSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_with_three_topics_creates_interest_rows(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $topics = PostCategory::factory()->count(3)->interestTopic()->create();

        $this->postJson("/api/v2/user/profile/interests", [
            "interests" => $topics->pluck("id")->all(),
        ])->assertStatus(200)->assertJson(["success" => true]);

        foreach ($topics as $topic) {
            $this->assertDatabaseHas("user_interests", [
                "user_id" => $user->id,
                "category_id" => $topic->id,
            ]);
        }
    }

    public function test_sync_rejects_fewer_than_three(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $topics = PostCategory::factory()->count(2)->interestTopic()->create();

        $this->postJson("/api/v2/user/profile/interests", [
            "interests" => $topics->pluck("id")->all(),
        ])->assertStatus(422)->assertJson(["success" => false]);

        $this->assertSame(0, UserInterest::where("user_id", $user->id)->count());
    }

    public function test_sync_rejects_nonexistent_category_id(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $topics = PostCategory::factory()->count(2)->interestTopic()->create();

        $this->postJson("/api/v2/user/profile/interests", [
            "interests" => [...$topics->pluck("id")->all(), 999999],
        ])->assertStatus(422)->assertJson(["success" => false]);

        $this->assertSame(0, UserInterest::where("user_id", $user->id)->count());
    }

    public function test_repeat_sync_does_not_duplicate_rows(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $topics = PostCategory::factory()->count(3)->interestTopic()->create();
        $ids = $topics->pluck("id")->all();

        $this->postJson("/api/v2/user/profile/interests", ["interests" => $ids])->assertStatus(200);
        $this->postJson("/api/v2/user/profile/interests", ["interests" => $ids])->assertStatus(200);

        $this->assertSame(3, UserInterest::where("user_id", $user->id)->count());
    }

    public function test_sync_requires_authentication(): void
    {
        $this->postJson("/api/v2/user/profile/interests", ["interests" => [1, 2, 3]])
            ->assertStatus(401);
    }
}
