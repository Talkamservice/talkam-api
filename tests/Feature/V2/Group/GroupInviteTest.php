<?php

namespace Tests\Feature\V2\Group;

use App\Models\Group;
use App\Models\Invitation;
use App\Models\User;
use App\Services\Invitation\InvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GroupInviteTest extends TestCase
{
    use RefreshDatabase;

    public function test_invite_creates_invitation_with_group_id(): void
    {
        $inviter = User::factory()->create();
        Sanctum::actingAs($inviter);
        $group = Group::factory()->create();

        $this->postJson("/api/v2/user/groups/{$group->id}/invite", [
            "email" => "friend@example.com",
        ])->assertStatus(200)->assertJson(["success" => true]);

        $this->assertDatabaseHas("invitations", [
            "invitee_email" => "friend@example.com",
            "group_id" => $group->id,
            "invited_by" => $inviter->id,
        ]);
    }

    public function test_accepting_invite_creates_membership(): void
    {
        $inviter = User::factory()->create();
        $invitee = User::factory()->create(["email" => "invitee@example.com"]);
        $group = Group::factory()->create();

        Sanctum::actingAs($inviter);
        $uuid = $this->postJson("/api/v2/user/groups/{$group->id}/invite", [
            "email" => "invitee@example.com",
        ])->json("data.uuid");

        Sanctum::actingAs($invitee);
        $this->postJson("/api/v2/user/groups/invites/accept", ["uuid" => $uuid])
            ->assertStatus(200)->assertJson(["success" => true]);

        $this->assertDatabaseHas("group_members", [
            "group_id" => $group->id,
            "user_id" => $invitee->id,
            "role" => "Member",
        ]);
        $this->assertDatabaseHas("invitations", ["uuid" => $uuid, "status" => "Active"]);
    }

    public function test_accept_rejects_wrong_email(): void
    {
        $inviter = User::factory()->create();
        $wrong_user = User::factory()->create();
        $group = Group::factory()->create();

        Sanctum::actingAs($inviter);
        $uuid = $this->postJson("/api/v2/user/groups/{$group->id}/invite", [
            "email" => "someoneelse@example.com",
        ])->json("data.uuid");

        Sanctum::actingAs($wrong_user);
        $this->postJson("/api/v2/user/groups/invites/accept", ["uuid" => $uuid])
            ->assertStatus(400)->assertJson(["success" => false]);

        $this->assertDatabaseMissing("group_members", ["user_id" => $wrong_user->id]);
    }

    public function test_v1_invite_flow_leaves_group_id_null(): void
    {
        $admin = User::factory()->create();
        $role = Role::create(["name" => "member-role"]);

        // v1 app-level invite path (service-level; the web flow drives this).
        InvitationService::invite([
            "invited_by" => $admin->id,
            "role_id" => $role->id,
            "source" => "admin",
            "status" => "Active",
        ], ["v1invitee@example.com"]);

        $invite = Invitation::where("invitee_email", "v1invitee@example.com")->first();
        $this->assertNotNull($invite);
        $this->assertNull($invite->group_id);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson("/api/v2/user/groups/1/invite", [])->assertStatus(401);
        $this->postJson("/api/v2/user/groups/invites/accept", [])->assertStatus(401);
    }
}
