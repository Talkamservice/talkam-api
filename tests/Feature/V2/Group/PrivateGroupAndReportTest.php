<?php

namespace Tests\Feature\V2\Group;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrivateGroupAndReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_access_creates_pending_request(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $group = Group::factory()->closed()->create();

        $this->postJson("/api/v2/user/groups/{$group->id}/request-access")
            ->assertStatus(200)->assertJson(["success" => true]);

        $this->assertDatabaseHas("group_members", [
            "group_id" => $group->id,
            "user_id" => $user->id,
            "status" => "Pending",
        ]);
    }

    public function test_update_access_request_changes_state(): void
    {
        Notification::fake();
        $admin = User::factory()->create();
        Sanctum::actingAs($admin);
        $group = Group::factory()->closed()->create();
        $member = GroupMember::factory()->create([
            "group_id" => $group->id,
            "status" => "Pending",
        ]);

        $this->postJson("/api/v2/user/groups/{$group->id}/update-access-request", [
            "member_id" => $member->id,
            "action" => "Approved",
        ])->assertStatus(200);

        $this->assertDatabaseHas("group_members", [
            "id" => $member->id,
            "status" => "Active",
        ]);
    }

    public function test_report_group_creates_report_row(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $group = Group::factory()->create();

        $this->postJson("/api/v2/user/groups/reports/create", [
            "group_id" => $group->id,
            "reason" => "Inappropriate content",
        ])->assertStatus(200);

        $this->assertDatabaseHas("group_reports", ["group_id" => $group->id]);
    }

    public function test_report_member_creates_report_row(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $member = GroupMember::factory()->create();

        $this->postJson("/api/v2/user/groups/reports/members/create", [
            "group_member_id" => $member->id,
            "reason" => "Spamming the group",
        ])->assertStatus(200);

        $this->assertDatabaseHas("group_member_reports", ["group_member_id" => $member->id]);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson("/api/v2/user/groups/1/request-access")->assertStatus(401);
        $this->postJson("/api/v2/user/groups/1/update-access-request", [])->assertStatus(401);
        $this->postJson("/api/v2/user/groups/reports/create", [])->assertStatus(401);
        $this->postJson("/api/v2/user/groups/reports/members/create", [])->assertStatus(401);
    }
}
