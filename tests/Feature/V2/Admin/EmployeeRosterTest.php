<?php

namespace Tests\Feature\V2\Admin;

use App\Constants\Business\OrganizationConstants;
use App\Constants\General\StatusConstants;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeRosterTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create(["seats_licensed" => 10]);
        $this->admin = User::factory()->create();

        OrganizationMember::factory()->admin()->create([
            "organization_id" => $this->organization->id,
            "user_id" => $this->admin->id,
        ]);

        Sanctum::actingAs($this->admin);
    }

    private function member(string $department = "Technology", string $status = OrganizationConstants::MEMBER_ACTIVE): OrganizationMember
    {
        return OrganizationMember::factory()->create([
            "organization_id" => $this->organization->id,
            "user_id" => User::factory()->create()->id,
            "role" => OrganizationConstants::ROLE_EMPLOYEE,
            "department" => $department,
            "status" => $status,
        ]);
    }

    public function test_the_roster_merges_members_and_pending_invitations(): void
    {
        $member = $this->member();

        Invitation::create([
            "uuid" => "IV_ROSTER1",
            "invited_by" => $this->admin->id,
            "organization_id" => $this->organization->id,
            "invitee_email" => "pending@zenithbank.com",
            "invite_role" => "employee",
            "department" => "Finance",
            "source" => OrganizationConstants::INVITE_SOURCE,
            "status" => StatusConstants::PENDING,
        ]);

        $rows = collect(
            $this->getJson("/api/v2/business/employees")->assertStatus(200)->json("data.employees")
        );

        // The admin's own seat, the employee, and the invitation.
        $this->assertCount(3, $rows);

        $employee = $rows->firstWhere("member_id", $member->id);
        $this->assertSame("EMP-" . str_pad((string) $member->id, 4, "0", STR_PAD_LEFT), $employee["id"]);
        $this->assertSame("member", $employee["source"]);

        $invited = $rows->firstWhere("email", "pending@zenithbank.com");
        $this->assertSame(OrganizationConstants::MEMBER_INVITED, $invited["status"]);
        $this->assertSame("Finance", $invited["department"]);
        $this->assertSame("invitation", $invited["source"]);
    }

    public function test_the_display_id_is_stable_across_requests(): void
    {
        $member = $this->member();

        $first = collect($this->getJson("/api/v2/business/employees")->json("data.employees"))
            ->firstWhere("member_id", $member->id)["id"];
        $second = collect($this->getJson("/api/v2/business/employees")->json("data.employees"))
            ->firstWhere("member_id", $member->id)["id"];

        $this->assertSame($first, $second);
    }

    public function test_the_roster_filters_by_department_status_and_search(): void
    {
        $tech = $this->member("Technology");
        $this->member("Finance");
        $this->member("Finance", OrganizationConstants::MEMBER_INACTIVE);

        $byDept = $this->getJson("/api/v2/business/employees?department=Finance")->json("data.employees");
        $this->assertCount(2, $byDept);

        $byStatus = $this->getJson("/api/v2/business/employees?status=inactive")->json("data.employees");
        $this->assertCount(1, $byStatus);

        $email = User::find($tech->user_id)->email;
        $bySearch = $this->getJson("/api/v2/business/employees?search=" . urlencode($email))->json("data.employees");
        $this->assertCount(1, $bySearch);
    }

    public function test_departments_come_from_what_is_in_use(): void
    {
        $this->member("Technology");
        $this->member("Legal");

        $departments = $this->getJson("/api/v2/business/employees")->json("data.departments");

        $this->assertContains("Technology", $departments);
        $this->assertContains("Legal", $departments);
    }

    /* ── Seats ──────────────────────────────────────────────────────────── */

    public function test_deactivating_frees_a_seat_and_reactivating_restores_it(): void
    {
        $member = $this->member();
        $before = $this->organization->seatsUsed();

        $this->postJson("/api/v2/business/employees/{$member->id}/deactivate")
            ->assertStatus(200)
            ->assertJsonPath("data.status", OrganizationConstants::MEMBER_INACTIVE);

        $this->assertSame($before - 1, $this->organization->refresh()->seatsUsed());

        $this->postJson("/api/v2/business/employees/{$member->id}/reactivate")
            ->assertStatus(200)
            ->assertJsonPath("data.status", OrganizationConstants::MEMBER_ACTIVE);

        $this->assertSame($before, $this->organization->refresh()->seatsUsed());
    }

    public function test_deactivating_twice_is_rejected(): void
    {
        $member = $this->member();

        $this->postJson("/api/v2/business/employees/{$member->id}/deactivate")->assertStatus(200);
        $this->postJson("/api/v2/business/employees/{$member->id}/deactivate")->assertStatus(400);
    }

    public function test_an_admin_seat_cannot_be_deactivated_from_the_dashboard(): void
    {
        $membership = OrganizationMember::where("user_id", $this->admin->id)->first();

        $this->postJson("/api/v2/business/employees/{$membership->id}/deactivate")
            ->assertStatus(400);

        $this->assertSame(
            OrganizationConstants::MEMBER_ACTIVE,
            $membership->refresh()->status
        );
    }

    public function test_reactivating_beyond_the_licence_is_rejected(): void
    {
        $this->organization->update(["seats_licensed" => 2]);

        $member = $this->member();
        $this->postJson("/api/v2/business/employees/{$member->id}/deactivate")->assertStatus(200);

        // Fill the freed seat.
        $this->member();

        $this->postJson("/api/v2/business/employees/{$member->id}/reactivate")
            ->assertStatus(400);

        $this->assertSame(
            OrganizationConstants::MEMBER_INACTIVE,
            $member->refresh()->status
        );
    }

    public function test_another_tenants_member_cannot_be_deactivated(): void
    {
        $other = Organization::factory()->create();
        $foreign = OrganizationMember::factory()->create([
            "organization_id" => $other->id,
            "user_id" => User::factory()->create()->id,
            "role" => OrganizationConstants::ROLE_EMPLOYEE,
        ]);

        $this->postJson("/api/v2/business/employees/{$foreign->id}/deactivate")
            ->assertStatus(404);

        $this->assertSame(
            OrganizationConstants::MEMBER_ACTIVE,
            $foreign->refresh()->status
        );
    }

    public function test_the_csv_export_has_the_roster_columns_only(): void
    {
        $this->member();

        $csv = $this->get("/api/v2/business/employees/export")->assertStatus(200)->streamedContent();
        $header = explode("\n", trim($csv))[0];

        $this->assertSame("ID,Email,Department,Role,Status,Activated", trim($header));
    }
}
