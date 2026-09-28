<?php

namespace Tests\Feature\V2\Group;

use App\Models\Group;
use App\Models\GroupFollow;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeaveAndFollowGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_leave_removes_membership_row(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $group = Group::factory()->create();
        GroupMember::factory()->create(["group_id" => $group->id, "user_id" => $user->id]);

        $this->postJson("/api/v2/user/groups/unfollow-group", [
            "group_id" => $group->id,
            "user_id" => $user->id,
        ])->assertStatus(200);

        $this->assertDatabaseMissing("group_members", [
            "group_id" => $group->id,
            "user_id" => $user->id,
        ]);
    }

    public function test_follow_group_does_not_create_membership(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $group = Group::factory()->create();

        $this->postJson("/api/v2/user/groups/follow", ["group_id" => $group->id])
            ->assertStatus(200)
            ->assertJsonPath("data.following", true);

        $this->assertDatabaseHas("group_follows", ["group_id" => $group->id, "user_id" => $user->id]);
        $this->assertDatabaseMissing("group_members", ["group_id" => $group->id, "user_id" => $user->id]);
    }

    public function test_follow_toggle_removes_on_second_call(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $group = Group::factory()->create();

        $this->postJson("/api/v2/user/groups/follow", ["group_id" => $group->id]);
        $this->postJson("/api/v2/user/groups/follow", ["group_id" => $group->id])
            ->assertJsonPath("data.following", false);

        $this->assertSame(0, GroupFollow::count());
    }

    public function test_following_endpoint_lists_followed_groups(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $followed = Group::factory()->create();
        $other = Group::factory()->create();
        GroupFollow::create(["user_id" => $user->id, "group_id" => $followed->id]);

        $ids = collect($this->getJson("/api/v2/user/groups/members/following")->assertStatus(200)->json("data.data"))
            ->pluck("id");
        $this->assertTrue($ids->contains($followed->id));
        $this->assertFalse($ids->contains($other->id));
    }

    public function test_requires_authentication(): void
    {
        $this->postJson("/api/v2/user/groups/follow", [])->assertStatus(401);
        $this->postJson("/api/v2/user/groups/unfollow-group", [])->assertStatus(401);
        $this->getJson("/api/v2/user/groups/members/following")->assertStatus(401);
    }
}
