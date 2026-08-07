<?php

namespace Tests\Feature\V2\User;

use App\Models\User;
use App\Models\UserFollow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FollowToggleTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_toggle_creates_follow_row(): void
    {
        $user = User::factory()->create();
        $author = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/user/follows/toggle", ["user_id" => $author->id])
            ->assertStatus(200)
            ->assertJsonPath("data.following", true);

        $this->assertDatabaseHas("user_follows", [
            "follower_id" => $user->id,
            "followed_id" => $author->id,
        ]);
    }

    public function test_second_toggle_removes_follow_row(): void
    {
        $user = User::factory()->create();
        $author = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/user/follows/toggle", ["user_id" => $author->id]);
        $this->postJson("/api/v2/user/follows/toggle", ["user_id" => $author->id])
            ->assertJsonPath("data.following", false);

        $this->assertSame(0, UserFollow::count());
    }

    public function test_duplicate_follow_never_creates_second_row(): void
    {
        $user = User::factory()->create();
        $author = User::factory()->create();
        Sanctum::actingAs($user);

        // Toggle an odd number of times — exactly one row must remain.
        $this->postJson("/api/v2/user/follows/toggle", ["user_id" => $author->id]);
        $this->postJson("/api/v2/user/follows/toggle", ["user_id" => $author->id]);
        $this->postJson("/api/v2/user/follows/toggle", ["user_id" => $author->id]);

        $this->assertSame(1, UserFollow::where([
            "follower_id" => $user->id,
            "followed_id" => $author->id,
        ])->count());
    }

    public function test_cannot_follow_self(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/user/follows/toggle", ["user_id" => $user->id])
            ->assertStatus(400)->assertJson(["success" => false]);

        $this->assertSame(0, UserFollow::count());
    }

    public function test_following_endpoint_lists_followed_users(): void
    {
        $user = User::factory()->create();
        $author = User::factory()->create();
        UserFollow::create(["follower_id" => $user->id, "followed_id" => $author->id]);
        Sanctum::actingAs($user);

        $ids = collect($this->getJson("/api/v2/user/follows/following")->assertStatus(200)->json("data"))
            ->pluck("id");
        $this->assertTrue($ids->contains($author->id));
    }

    public function test_followers_endpoint_lists_followers(): void
    {
        $user = User::factory()->create();
        $fan = User::factory()->create();
        UserFollow::create(["follower_id" => $fan->id, "followed_id" => $user->id]);
        Sanctum::actingAs($user);

        $ids = collect($this->getJson("/api/v2/user/follows/followers")->assertStatus(200)->json("data"))
            ->pluck("id");
        $this->assertTrue($ids->contains($fan->id));
    }

    public function test_requires_authentication(): void
    {
        $this->postJson("/api/v2/user/follows/toggle", [])->assertStatus(401);
        $this->getJson("/api/v2/user/follows/following")->assertStatus(401);
        $this->getJson("/api/v2/user/follows/followers")->assertStatus(401);
    }
}
