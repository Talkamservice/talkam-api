<?php

namespace Tests\Feature\V2\Employee;

use App\Constants\Business\OrganizationConstants;
use App\Models\MoodCheckin;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Therapist;
use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The privacy boundary for §02's additions.
 *
 * Everything this section stores about an individual — check-in factors, notes,
 * pre/post session mood, the care-team note — must be unreachable by the
 * employer. There is no id parameter on any of these endpoints, so the test is
 * partly "prove no admin route returns it" and partly "prove an admin calling
 * these gets their OWN data, not a member's".
 */
class EmployeePrivacyTest extends TestCase
{
    use RefreshDatabase;

    private array $org;

    protected function setUp(): void
    {
        parent::setUp();

        $organization = Organization::factory()->create();

        $admin = User::factory()->create();
        OrganizationMember::factory()->admin()->create([
            "organization_id" => $organization->id,
            "user_id" => $admin->id,
        ]);

        $employee = User::factory()->create();
        OrganizationMember::factory()->create([
            "organization_id" => $organization->id,
            "user_id" => $employee->id,
            "role" => OrganizationConstants::ROLE_EMPLOYEE,
        ]);

        $this->org = compact("organization", "admin", "employee");
    }

    /** Sensitive strings only the employee should ever be able to retrieve. */
    private function seedEmployeeData(): void
    {
        $employee = $this->org["employee"];

        MoodCheckin::create([
            "user_id" => $employee->id,
            "mood" => 1,
            "checked_in_on" => now()->toDateString(),
            "factors" => ["work", "sleep"],
            "note" => "SECRET-NOTE-burnt-out-and-considering-quitting",
        ]);

        TherapySession::factory()->completed()->create([
            "user_id" => $employee->id,
            "therapist_id" => Therapist::factory()->create()->id,
            "client_pre_mood" => 1,
            "client_post_mood" => 2,
        ]);
    }

    public function test_an_admin_calling_the_member_endpoints_sees_only_their_own_empty_data(): void
    {
        $this->seedEmployeeData();
        Sanctum::actingAs($this->org["admin"]);

        // Same endpoints, but scoped to the caller — the admin has no check-ins
        // and no sessions of their own.
        $summary = $this->getJson("/api/v2/user/mood-checkins/summary")
            ->assertStatus(200)
            ->json("data");
        $this->assertSame(0, $summary["days_logged"]);
        $this->assertSame([], $summary["top_factors"]);

        $this->getJson("/api/v2/user/mood-checkins")
            ->assertStatus(200)
            ->assertJsonPath("data.data", []);

        $this->getJson("/api/v2/user/care-team")
            ->assertStatus(200)
            ->assertJsonPath("data.therapist", null);

        $bookings = $this->getJson("/api/v2/user/bookings")->assertStatus(200)->json("data");
        $this->assertSame([], $bookings["past"]);
        $this->assertSame(0, $bookings["summary"]["completed"]);
    }

    /**
     * The load-bearing assertion: no admin-facing surface anywhere in the org
     * lane may contain an individual's check-in note, factors or session mood.
     */
    public function test_no_admin_surface_contains_individual_wellbeing_data(): void
    {
        $this->seedEmployeeData();
        Sanctum::actingAs($this->org["admin"]);

        $admin_endpoints = [
            "/api/v2/business/organization",
            "/api/v2/business/invitations",
            "/api/v2/user/me",
        ];

        foreach ($admin_endpoints as $uri) {
            $body = $this->getJson($uri)->assertStatus(200)->getContent();

            foreach ([
                "SECRET-NOTE",
                "client_pre_mood",
                "client_post_mood",
                "\"factors\"",
                "\"top_factors\"",
            ] as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $body,
                    "{$uri} leaked {$needle}"
                );
            }
        }
    }

    public function test_an_employee_cannot_read_another_employees_checkins(): void
    {
        $this->seedEmployeeData();

        $colleague = User::factory()->create();
        OrganizationMember::factory()->create([
            "organization_id" => $this->org["organization"]->id,
            "user_id" => $colleague->id,
            "role" => OrganizationConstants::ROLE_EMPLOYEE,
        ]);

        Sanctum::actingAs($colleague);

        $response = $this->getJson("/api/v2/user/mood-checkins")->assertStatus(200);
        $this->assertSame([], $response->json("data.data"));
        $this->assertStringNotContainsString("SECRET-NOTE", $response->getContent());

        $this->assertSame(
            0,
            $this->getJson("/api/v2/user/mood-checkins/summary")->json("data.days_logged")
        );
    }

    /** The employee themselves must of course still get their own data back. */
    public function test_the_employee_can_read_their_own_data(): void
    {
        $this->seedEmployeeData();
        Sanctum::actingAs($this->org["employee"]);

        $history = $this->getJson("/api/v2/user/mood-checkins")->json("data.data");
        $this->assertCount(1, $history);
        $this->assertSame("SECRET-NOTE-burnt-out-and-considering-quitting", $history[0]["note"]);
        $this->assertSame(["work", "sleep"], $history[0]["factors"]);

        $this->assertSame(
            1,
            $this->getJson("/api/v2/user/mood-checkins/summary")->json("data.days_logged")
        );

        $past = $this->getJson("/api/v2/user/bookings")->json("data.past");
        $this->assertSame(1, $past[0]["client_pre_mood"]);
    }

    public function test_every_member_endpoint_requires_authentication(): void
    {
        foreach ([
            "/api/v2/user/mood-checkins",
            "/api/v2/user/mood-checkins/summary",
            "/api/v2/user/mood-checkins/today",
            "/api/v2/user/care-team",
            "/api/v2/user/community/trending",
            "/api/v2/user/bookings",
        ] as $uri) {
            $this->getJson($uri)->assertStatus(401, "{$uri} should require auth");
        }
    }
}
