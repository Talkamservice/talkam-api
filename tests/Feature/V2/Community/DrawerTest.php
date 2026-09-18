<?php

namespace Tests\Feature\V2\Community;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\PostCategory;
use App\Models\User;
use App\Models\UserFollow;
use App\Models\UserInterest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DrawerTest extends TestCase
{
    use RefreshDatabase;

    public function test_drawer_returns_all_blocks_in_one_response(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson("/api/v2/user/drawer")
            ->assertStatus(200)
            ->assertJson(["success" => true, "code" => 200])
            ->assertJsonStructure(["data" => [
                "profile" => ["name", "username", "avatar", "is_verified"],
                "following_count", "followers_count", "topics", "groups", "private_groups",
            ]]);
    }

    public function test_follow_counts_match_user_follows_rows(): void
    {
        $user = User::factory()->create();
        UserFollow::create(["follower_id" => $user->id, "followed_id" => User::factory()->create()->id]);
        UserFollow::create(["follower_id" => $user->id, "followed_id" => User::factory()->create()->id]);
        UserFollow::create(["follower_id" => User::factory()->create()->id, "followed_id" => $user->id]);

        Sanctum::actingAs($user);
        $this->getJson("/api/v2/user/drawer")
            ->assertJsonPath("data.following_count", 2)
            ->assertJsonPath("data.followers_count", 1);
    }

    public function test_topics_and_groups_blocks_match_membership_rows(): void
    {
        $user = User::factory()->create();
        $topic = PostCategory::factory()->interestTopic()->create(["name" => "Anxiety"]);
        UserInterest::create(["user_id" => $user->id, "category_id" => $topic->id]);

        $open_group = Group::factory()->create();
        $private_group = Group::factory()->closed()->create();
        $foreign_group = Group::factory()->create();
        GroupMember::factory()->create(["group_id" => $open_group->id, "user_id" => $user->id]);
        GroupMember::factory()->create(["group_id" => $private_group->id, "user_id" => $user->id]);
        GroupMember::factory()->create(["group_id" => $foreign_group->id]); // someone else's

        Sanctum::actingAs($user);
        $data = $this->getJson("/api/v2/user/drawer")->json("data");

        $this->assertEquals(["Anxiety"], collect($data["topics"])->pluck("name")->all());
        $this->assertEquals([$open_group->id], collect($data["groups"])->pluck("id")->all());
        $this->assertEquals([$private_group->id], collect($data["private_groups"])->pluck("id")->all());
    }

    public function test_guest_gets_401(): void
    {
        $this->getJson("/api/v2/user/drawer")->assertStatus(401);
    }
}
