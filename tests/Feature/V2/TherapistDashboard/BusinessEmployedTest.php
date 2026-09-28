<?php

namespace Tests\Feature\V2\TherapistDashboard;

use App\Constants\Business\OrganizationConstants;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Therapist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessEmployedTest extends TestCase
{
    use RefreshDatabase;

    private function therapistUser(): User
    {
        $user = User::factory()->create();
        Therapist::factory()->create(["user_id" => $user->id]);

        return $user;
    }

    public function test_a_business_employed_therapist_is_flagged_and_blocked_from_earnings(): void
    {
        $user = $this->therapistUser();
        $organization = Organization::factory()->create(["name" => "Zenith Bank Nigeria"]);

        OrganizationMember::factory()->therapist()->create([
            "organization_id" => $organization->id,
            "user_id" => $user->id,
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/v2/therapist/home")
            ->assertStatus(200)
            ->assertJsonPath("data.employment.is_business_employed", true)
            ->assertJsonPath("data.employment.employer_name", "Zenith Bank Nigeria");

        // Earnings is a 403-with-explanation, not a zeroed dashboard.
        $this->getJson("/api/v2/therapist/earnings/dashboard")
            ->assertStatus(403)
            ->assertJsonPath("success", false);

        $this->getJson("/api/v2/therapist/earnings/transactions")->assertStatus(403);
    }

    public function test_a_network_therapist_still_gets_their_earnings(): void
    {
        $user = $this->therapistUser();
        Sanctum::actingAs($user);

        $this->getJson("/api/v2/therapist/home")
            ->assertStatus(200)
            ->assertJsonPath("data.employment.is_business_employed", false)
            ->assertJsonPath("data.employment.employer_name", null);

        $this->getJson("/api/v2/therapist/earnings/dashboard")
            ->assertStatus(200)
            ->assertJsonPath("success", true);
    }

    /** An org member with a NON-therapist role does not flip the flag. */
    public function test_an_employee_membership_does_not_count_as_business_employment(): void
    {
        $user = $this->therapistUser();
        $organization = Organization::factory()->create();

        OrganizationMember::factory()->create([
            "organization_id" => $organization->id,
            "user_id" => $user->id,
            "role" => OrganizationConstants::ROLE_EMPLOYEE,
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/v2/therapist/home")
            ->assertStatus(200)
            ->assertJsonPath("data.employment.is_business_employed", false);

        $this->getJson("/api/v2/therapist/earnings/dashboard")->assertStatus(200);
    }
}
