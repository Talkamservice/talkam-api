<?php

namespace Tests\Feature\V2\Admin;

use App\Constants\Business\OrganizationConstants;
use App\Constants\General\StatusConstants;
use App\Models\MoodCheckin;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Therapist;
use App\Models\TherapySession;
use App\Models\User;
use App\Models\UserReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Role separation and tenant isolation across the whole admin surface. */
class AdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /** [method, uri, payload] for every admin-only route in §03. */
    private function endpoints(int $member_id = 1): array
    {
        return [
            ["get", "/api/v2/business/insights/overview", []],
            ["get", "/api/v2/business/insights/team-needs", []],
            ["get", "/api/v2/business/reports", []],
            ["get", "/api/v2/business/reports/usage/download", []],
            ["get", "/api/v2/business/employees", []],
            ["get", "/api/v2/business/employees/export", []],
            ["post", "/api/v2/business/employees/{$member_id}/deactivate", []],
            ["post", "/api/v2/business/employees/{$member_id}/reactivate", []],
            ["get", "/api/v2/business/therapists", []],
            ["get", "/api/v2/business/safety-reports", []],
            ["get", "/api/v2/business/activity", []],
            ["post", "/api/v2/business/organization/profile", ["name" => "Renamed Corp"]],
        ];
    }

    private function hit(string $method, string $uri, array $payload)
    {
        return $method === "get" ? $this->getJson($uri) : $this->postJson($uri, $payload);
    }

    private function memberOf(Organization $organization, string $role): User
    {
        $user = User::factory()->create();

        OrganizationMember::factory()->create([
            "organization_id" => $organization->id,
            "user_id" => $user->id,
            "role" => $role,
        ]);

        return $user;
    }

    public function test_an_employee_is_refused_every_admin_endpoint(): void
    {
        $organization = Organization::factory()->create();
        Sanctum::actingAs($this->memberOf($organization, OrganizationConstants::ROLE_EMPLOYEE));

        foreach ($this->endpoints() as [$method, $uri, $payload]) {
            $this->hit($method, $uri, $payload)->assertStatus(403, "{$method} {$uri}");
        }
    }

    public function test_a_therapist_is_refused_every_admin_endpoint(): void
    {
        $organization = Organization::factory()->create();
        Sanctum::actingAs($this->memberOf($organization, OrganizationConstants::ROLE_THERAPIST));

        foreach ($this->endpoints() as [$method, $uri, $payload]) {
            $this->hit($method, $uri, $payload)->assertStatus(403, "{$method} {$uri}");
        }
    }

    public function test_a_non_member_is_refused_every_admin_endpoint(): void
    {
        Sanctum::actingAs(User::factory()->create());

        foreach ($this->endpoints() as [$method, $uri, $payload]) {
            $this->hit($method, $uri, $payload)->assertStatus(403, "{$method} {$uri}");
        }
    }

    public function test_unauthenticated_callers_get_401(): void
    {
        foreach ($this->endpoints() as [$method, $uri, $payload]) {
            $this->hit($method, $uri, $payload)->assertStatus(401, "{$method} {$uri}");
        }
    }

    /* ── Tenant isolation ───────────────────────────────────────────────── */

    public function test_an_admin_sees_only_their_own_organizations_data(): void
    {
        $mine = Organization::factory()->create(["seats_licensed" => 50, "name" => "Mine Ltd"]);
        $theirs = Organization::factory()->create(["seats_licensed" => 50, "name" => "Theirs Ltd"]);

        $admin = $this->memberOf($mine, OrganizationConstants::ROLE_ADMIN);
        $therapist = Therapist::factory()->create();

        // Six employees in EACH org, so both are above the suppression floor and
        // a leak would show up as a real number rather than a null.
        foreach ([$mine, $theirs] as $organization) {
            for ($i = 0; $i < 6; $i++) {
                $user = User::factory()->create();
                OrganizationMember::factory()->create([
                    "organization_id" => $organization->id,
                    "user_id" => $user->id,
                    "role" => OrganizationConstants::ROLE_EMPLOYEE,
                    "department" => $organization->id === $mine->id ? "Technology" : "SecretDept",
                ]);

                MoodCheckin::create([
                    "user_id" => $user->id,
                    "mood" => 3,
                    "checked_in_on" => now()->subDays($i)->toDateString(),
                    "factors" => ["work"],
                ]);

                TherapySession::factory()->completed()->create([
                    "user_id" => $user->id,
                    "therapist_id" => $therapist->id,
                    "starts_at" => now()->startOfMonth()->addDay(),
                ]);
            }
        }

        Sanctum::actingAs($admin);

        // Overview counts only my six, not the other org's six.
        $overview = $this->getJson("/api/v2/business/insights/overview")->assertStatus(200)->json("data");
        $this->assertSame(6, $overview["cohort"]);
        $this->assertSame(6, $overview["kpis"]["sessions_this_month"]["value"]);

        $departments = array_column($overview["departments"], "department");
        $this->assertContains("Technology", $departments);
        $this->assertNotContains("SecretDept", $departments);

        // Roster is mine only: 1 admin + 6 employees.
        $roster = $this->getJson("/api/v2/business/employees")->json("data.employees");
        $this->assertCount(7, $roster);

        // The other company's name never appears.
        $this->assertStringNotContainsString(
            "Theirs Ltd",
            $this->getJson("/api/v2/business/organization")->getContent()
        );
    }

    public function test_safety_reports_are_scoped_to_the_organization(): void
    {
        $mine = Organization::factory()->create();
        $theirs = Organization::factory()->create();

        $admin = $this->memberOf($mine, OrganizationConstants::ROLE_ADMIN);
        $mine_employee = $this->memberOf($mine, OrganizationConstants::ROLE_EMPLOYEE);
        $their_employee = $this->memberOf($theirs, OrganizationConstants::ROLE_EMPLOYEE);
        $therapist = User::factory()->create();

        UserReport::create([
            "reporter_id" => $mine_employee->id,
            "reported_user_id" => $therapist->id,
            "reason" => "Mine report",
            "status" => StatusConstants::PENDING,
        ]);
        UserReport::create([
            "reporter_id" => $their_employee->id,
            "reported_user_id" => $therapist->id,
            "reason" => "Their report",
            "status" => StatusConstants::PENDING,
        ]);

        Sanctum::actingAs($admin);

        $reports = $this->getJson("/api/v2/business/safety-reports")->assertStatus(200)->json("data.reports");

        $this->assertCount(1, $reports);
        $this->assertSame("Mine report", $reports[0]["category"]);
    }

    public function test_the_activity_log_is_scoped_to_the_organizations_own_admins(): void
    {
        $mine = Organization::factory()->create();
        $theirs = Organization::factory()->create();

        $admin = $this->memberOf($mine, OrganizationConstants::ROLE_ADMIN);
        $their_admin = $this->memberOf($theirs, OrganizationConstants::ROLE_ADMIN);

        \App\Models\ActivityLog::create([
            "admin_id" => $admin->id,
            "title" => "Mine",
            "description" => "MY-ACTION",
            "activity" => "test",
            "source" => "web",
            "event" => "updated",
            "channel" => "web",
            "type" => "system",
        ]);
        \App\Models\ActivityLog::create([
            "admin_id" => $their_admin->id,
            "title" => "Theirs",
            "description" => "THEIR-ACTION",
            "activity" => "test",
            "source" => "web",
            "event" => "updated",
            "channel" => "web",
            "type" => "system",
        ]);

        Sanctum::actingAs($admin);

        $body = $this->getJson("/api/v2/business/activity")->assertStatus(200)->getContent();

        $this->assertStringContainsString("MY-ACTION", $body);
        $this->assertStringNotContainsString("THEIR-ACTION", $body);
    }

    /* ── Company profile ────────────────────────────────────────────────── */

    public function test_an_admin_can_edit_the_company_profile_but_not_the_domain(): void
    {
        $organization = Organization::factory()->create(["domain" => "locked.com"]);
        Sanctum::actingAs($this->memberOf($organization, OrganizationConstants::ROLE_ADMIN));

        $this->postJson("/api/v2/business/organization/profile", [
            "name" => "Renamed Corp",
            "industry" => "Technology",
            "hr_contact_email" => "people@locked.com",
            // The tenancy key — must be ignored.
            "domain" => "hijacked.com",
        ])
            ->assertStatus(200)
            ->assertJsonPath("data.organization.name", "Renamed Corp")
            ->assertJsonPath("data.organization.industry", "Technology");

        $this->assertSame("locked.com", $organization->refresh()->domain);
    }

    public function test_the_company_profile_rejects_bad_input(): void
    {
        $organization = Organization::factory()->create(["name" => "Original"]);
        Sanctum::actingAs($this->memberOf($organization, OrganizationConstants::ROLE_ADMIN));

        $this->postJson("/api/v2/business/organization/profile", ["name" => "x"])->assertStatus(422);
        $this->postJson("/api/v2/business/organization/profile", ["hr_contact_email" => "nope"])->assertStatus(422);

        $this->assertSame("Original", $organization->refresh()->name);
    }
}
