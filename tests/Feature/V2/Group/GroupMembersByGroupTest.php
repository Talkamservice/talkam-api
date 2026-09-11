<?php

namespace Tests\Feature\V2\Group;

use App\Constants\Account\User\UserConstants;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * GET /user/groups/{group}/members — path-addressed wrapper around
 * GroupMemberController@index's existing ?group_id= listing, which already
 * groups members by role (Owner / Admin=moderator / Member).
 */
class GroupMembersByGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_members_grouped_by_role(): void
    {
        $group = Group::factory()->create();
        $owner = GroupMember::factory()->create(["group_id" => $group->id, "role" => UserConstants::OWNER]);
        $moderator = GroupMember::factory()->create(["group_id" => $group->id, "role" => UserConstants::ADMIN]);
        $member = GroupMember::factory()->create(["group_id" => $group->id, "role" => UserConstants::MEMBER]);

        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson("/api/v2/user/groups/{$group->id}/members")->assertStatus(200);

        $ownerIds = collect($response->json("data.Owner"))->pluck("id")->all();
        $moderatorIds = collect($response->json("data.Admin"))->pluck("id")->all();
        $memberIds = collect($response->json("data.Member"))->pluck("id")->all();

        $this->assertSame([$owner->id], $ownerIds);
        $this->assertSame([$moderator->id], $moderatorIds);
        $this->assertSame([$member->id], $memberIds);
    }

    public function test_excludes_members_of_other_groups(): void
    {
        $group = Group::factory()->create();
        $other = Group::factory()->create();
        $mine = GroupMember::factory()->create(["group_id" => $group->id]);
        GroupMember::factory()->create(["group_id" => $other->id]);

        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson("/api/v2/user/groups/{$group->id}/members")->assertStatus(200);

        $memberIds = collect($response->json("data.Member"))->pluck("id")->all();
        $this->assertSame([$mine->id], $memberIds);
    }

    public function test_unknown_group_returns_error(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v2/user/groups/999999/members")->assertStatus(400);
    }

    public function test_requires_authentication(): void
    {
        $group = Group::factory()->create();

        $this->getJson("/api/v2/user/groups/{$group->id}/members")->assertStatus(401);
    }
}
