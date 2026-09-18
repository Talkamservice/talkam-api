<?php

namespace Tests\Feature\V2\Business;

use App\Constants\General\StatusConstants;
use App\Constants\Invitation\InvitationConstants;
use App\Models\Group;
use App\Models\Invitation;
use App\Models\User;
use App\Services\Group\GroupInviteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * §01 adds organization_id + three columns to the shared invitations table.
 * These pin that neither the v1 admin invite flow nor the §05 group invite
 * flow starts writing them — the three lanes stay isolated by that FK.
 */
class V1InvitationRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_v1_admin_invite_flow_leaves_the_org_columns_null(): void
    {
        $inviter = User::factory()->create();
        $role = Role::create(["name" => "editor", "guard_name" => "web"]);

        $invite = \App\Services\Invitation\InvitationService::create([
            "invited_by" => $inviter->id,
            "invitee_email" => "someone@talkam.net",
            "role_id" => $role->id,
            "source" => InvitationConstants::SUPER_ADMIN,
            "status" => StatusConstants::PENDING,
        ]);

        $this->assertNull($invite->organization_id);
        $this->assertNull($invite->invite_role);
        $this->assertNull($invite->department);
        $this->assertNull($invite->opened_at);
        $this->assertNull($invite->group_id);
    }

    public function test_the_group_invite_flow_leaves_the_org_columns_null(): void
    {
        $inviter = User::factory()->create();
        $group = Group::factory()->create();

        $invite = (new GroupInviteService)->invite($inviter, $group->id, [
            "email" => "member@example.com",
        ]);

        $this->assertSame($group->id, $invite->group_id);
        $this->assertNull($invite->organization_id);
        $this->assertNull($invite->invite_role);
        $this->assertNull($invite->department);
    }

    public function test_org_reads_never_pick_up_non_org_invitations(): void
    {
        $inviter = User::factory()->create();

        Invitation::create([
            "uuid" => "IV_NOORG",
            "invited_by" => $inviter->id,
            "invitee_email" => "noorg@talkam.net",
            "source" => "admin",
            "status" => StatusConstants::PENDING,
        ]);

        $this->assertSame(0, Invitation::whereNotNull("organization_id")->count());
        $this->assertSame(1, Invitation::count());
    }
}
