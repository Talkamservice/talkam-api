<?php

namespace Tests\Feature\V2\Booking;

use App\Constants\Business\OrganizationConstants;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OrganizationTherapist;
use App\Models\Therapist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The booking picker (§ BookingModal) needs a business-employed member to see
 * their own org's therapists — own-brought-in AND network-added, whether or
 * not TalkAM-verified — without leaking into the open consumer directory or
 * across organizations.
 */
class TherapistDirectoryOrgScopeTest extends TestCase
{
    use RefreshDatabase;

    private function employeeOf(Organization $org): User
    {
        $employee = User::factory()->create();
        OrganizationMember::factory()->create([
            "organization_id" => $org->id,
            "user_id" => $employee->id,
            "role" => OrganizationConstants::ROLE_EMPLOYEE,
            "status" => OrganizationConstants::MEMBER_ACTIVE,
        ]);

        return $employee;
    }

    public function test_employee_sees_orgs_own_unverified_therapist(): void
    {
        $org = Organization::factory()->create();
        $employee = $this->employeeOf($org);

        $own_user = User::factory()->create();
        $own = Therapist::factory()->create(["user_id" => $own_user->id, "verified_at" => null]);
        OrganizationMember::factory()->create([
            "organization_id" => $org->id,
            "user_id" => $own_user->id,
            "role" => OrganizationConstants::ROLE_THERAPIST,
            "status" => OrganizationConstants::MEMBER_ACTIVE,
        ]);

        Sanctum::actingAs($employee);

        $ids = collect($this->getJson("/api/v2/user/therapists")->json("data.data"))->pluck("id");
        $this->assertTrue($ids->contains($own->id));

        // Reachable too — not just listed.
        $this->getJson("/api/v2/user/therapists/{$own->id}")->assertStatus(200);
    }

    public function test_employee_sees_orgs_network_added_unverified_therapist(): void
    {
        $org = Organization::factory()->create();
        $employee = $this->employeeOf($org);

        $networked = Therapist::factory()->create(["verified_at" => null]);
        OrganizationTherapist::create([
            "organization_id" => $org->id,
            "therapist_id" => $networked->id,
            "status" => OrganizationConstants::NETWORK_ACTIVE,
        ]);

        Sanctum::actingAs($employee);

        $ids = collect($this->getJson("/api/v2/user/therapists")->json("data.data"))->pluck("id");
        $this->assertTrue($ids->contains($networked->id));
    }

    public function test_removed_network_therapist_is_not_shown(): void
    {
        $org = Organization::factory()->create();
        $employee = $this->employeeOf($org);

        $removed = Therapist::factory()->create(["verified_at" => null]);
        OrganizationTherapist::create([
            "organization_id" => $org->id,
            "therapist_id" => $removed->id,
            "status" => OrganizationConstants::NETWORK_REMOVED,
        ]);

        Sanctum::actingAs($employee);

        $ids = collect($this->getJson("/api/v2/user/therapists")->json("data.data"))->pluck("id");
        $this->assertFalse($ids->contains($removed->id));
    }

    public function test_employee_does_not_see_another_orgs_therapists(): void
    {
        $org_a = Organization::factory()->create();
        $org_b = Organization::factory()->create();
        $employee = $this->employeeOf($org_a);

        $others_own = Therapist::factory()->create(["verified_at" => null]);
        OrganizationMember::factory()->create([
            "organization_id" => $org_b->id,
            "user_id" => $others_own->user_id,
            "role" => OrganizationConstants::ROLE_THERAPIST,
            "status" => OrganizationConstants::MEMBER_ACTIVE,
        ]);

        Sanctum::actingAs($employee);

        $ids = collect($this->getJson("/api/v2/user/therapists")->json("data.data"))->pluck("id");
        $this->assertFalse($ids->contains($others_own->id));
        $this->getJson("/api/v2/user/therapists/{$others_own->id}")->assertStatus(404);
    }

    public function test_plain_consumer_unaffected_by_org_scoping(): void
    {
        $org = Organization::factory()->create();
        $own = Therapist::factory()->create(["verified_at" => null]);
        OrganizationMember::factory()->create([
            "organization_id" => $org->id,
            "user_id" => $own->user_id,
            "role" => OrganizationConstants::ROLE_THERAPIST,
            "status" => OrganizationConstants::MEMBER_ACTIVE,
        ]);

        Sanctum::actingAs(User::factory()->create());

        $ids = collect($this->getJson("/api/v2/user/therapists")->json("data.data"))->pluck("id");
        $this->assertFalse($ids->contains($own->id));
    }
}
