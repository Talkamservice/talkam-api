<?php

namespace Tests\Feature\V2\Admin;

use App\Constants\Business\OrganizationConstants;
use App\Models\EmployeeSelfCheck;
use App\Models\MoodCheckin;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\SessionNote;
use App\Models\Therapist;
use App\Models\TherapySession;
use App\Models\User;
use App\Models\UserReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * "The employer sees anonymised, company-wide totals only."
 *
 * This file is the enforcement. It seeds an organization above the suppression
 * floor — so every aggregate is UNSUPPRESSED and the endpoints are returning
 * real numbers — then walks every admin endpoint and asserts that none of them
 * carries individual data.
 *
 * The sentinel strings are deliberately distinctive: if any of them ever appears
 * in an admin response, this fails loudly and names the endpoint.
 */
class AdminPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $admin;
    private array $employees = [];

    /** Strings only an individual's own screens should ever be able to show. */
    private const SENTINELS = [
        "SENTINEL-CHECKIN-NOTE",
        "SENTINEL-PRIVATE-CLINICAL-NOTE",
        "SENTINEL-REPORT-DESCRIPTION",
        "employee.one@sentinel.test",
    ];

    /** Keys that would betray individual behaviour if they appeared. */
    private const FORBIDDEN_KEYS = [
        "client_pre_mood",
        "client_post_mood",
        "mood",
        "checked_in_on",
        "starts_at",
        "session_id",
        "shared_note",
        "note",
        "last_active",
        "sessions_used_by_employee",
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create(["seats_licensed" => 100]);
        $this->admin = User::factory()->create();

        OrganizationMember::factory()->admin()->create([
            "organization_id" => $this->organization->id,
            "user_id" => $this->admin->id,
        ]);

        $therapist = Therapist::factory()->create();

        // Six employees, so nothing is suppressed and the endpoints are live.
        for ($i = 0; $i < 6; $i++) {
            $user = $i === 0
                ? User::factory()->create(["email" => "employee.one@sentinel.test"])
                : User::factory()->create();

            OrganizationMember::factory()->create([
                "organization_id" => $this->organization->id,
                "user_id" => $user->id,
                "role" => OrganizationConstants::ROLE_EMPLOYEE,
                "department" => "Technology",
            ]);

            MoodCheckin::create([
                "user_id" => $user->id,
                "mood" => 2,
                "checked_in_on" => now()->subDays($i)->toDateString(),
                "factors" => ["work", "sleep"],
                "note" => "SENTINEL-CHECKIN-NOTE",
            ]);

            $session = TherapySession::factory()->completed()->create([
                "user_id" => $user->id,
                "therapist_id" => $therapist->id,
                "starts_at" => now()->startOfMonth()->addDay(),
                "client_pre_mood" => 1,
                "client_post_mood" => 4,
            ]);

            SessionNote::create([
                "session_id" => $session->id,
                "therapist_id" => $therapist->id,
                "title" => "Clinical",
                "content" => "SENTINEL-PRIVATE-CLINICAL-NOTE",
                "status" => "final",
                "shared_with_client" => false,
            ]);

            EmployeeSelfCheck::create([
                "organization_id" => $this->organization->id,
                "user_id" => $user->id,
                "category" => OrganizationConstants::SELF_CHECK_WORK,
                "score" => 3,
                "answered_at" => now(),
            ]);

            $this->employees[] = $user;
        }

        UserReport::create([
            "reporter_id" => $this->employees[0]->id,
            "reported_user_id" => $therapist->user_id,
            "reason" => "Late to session",
            "context" => "SENTINEL-REPORT-DESCRIPTION",
            "status" => "Pending",
        ]);

        Sanctum::actingAs($this->admin);
    }

    /** Every GET an admin can reach. */
    private function adminEndpoints(): array
    {
        return [
            "/api/v2/business/insights/overview",
            "/api/v2/business/insights/team-needs",
            "/api/v2/business/reports",
            "/api/v2/business/employees",
            "/api/v2/business/therapists",
            "/api/v2/business/safety-reports",
            "/api/v2/business/activity",
            "/api/v2/business/organization",
            "/api/v2/business/invitations",
        ];
    }

    /* ── The load-bearing assertion ─────────────────────────────────────── */

    public function test_no_admin_endpoint_leaks_individual_data(): void
    {
        foreach ($this->adminEndpoints() as $uri) {
            $body = $this->getJson($uri)->assertStatus(200)->getContent();

            foreach (self::SENTINELS as $sentinel) {
                // The roster legitimately shows the email of a seat the admin
                // themselves invited; nothing else may carry it.
                if ($sentinel === "employee.one@sentinel.test" && $uri === "/api/v2/business/employees") {
                    continue;
                }

                $this->assertStringNotContainsString(
                    $sentinel,
                    $body,
                    "{$uri} leaked '{$sentinel}'"
                );
            }
        }
    }

    public function test_no_admin_endpoint_exposes_a_forbidden_field(): void
    {
        foreach ($this->adminEndpoints() as $uri) {
            $body = $this->getJson($uri)->assertStatus(200)->getContent();

            foreach (self::FORBIDDEN_KEYS as $key) {
                $this->assertStringNotContainsString(
                    "\"{$key}\"",
                    $body,
                    "{$uri} exposed the field '{$key}'"
                );
            }
        }
    }

    /** Report CSVs are aggregates too — the same rule applies to downloads. */
    public function test_report_downloads_leak_nothing(): void
    {
        foreach (["usage", "wellness", "roi"] as $key) {
            $csv = $this->get("/api/v2/business/reports/{$key}/download")
                ->assertStatus(200)
                ->streamedContent();

            foreach (self::SENTINELS as $sentinel) {
                $this->assertStringNotContainsString($sentinel, $csv, "{$key}.csv leaked '{$sentinel}'");
            }
        }

        $csv = $this->get("/api/v2/business/employees/export")->assertStatus(200)->streamedContent();

        $this->assertStringContainsString("employee.one@sentinel.test", $csv, "the roster export should list seats");
        $this->assertStringNotContainsString("SENTINEL-CHECKIN-NOTE", $csv);
        $this->assertStringNotContainsString("Sessions", $csv, "the roster export must carry no usage column");
    }

    /* ── The roster's specific carve-outs ───────────────────────────────── */

    public function test_the_roster_carries_no_usage_or_activity_data(): void
    {
        $rows = $this->getJson("/api/v2/business/employees")->assertStatus(200)->json("data.employees");

        $this->assertNotEmpty($rows);

        foreach ($rows as $row) {
            foreach (["used", "total", "sessions", "last_active", "lastActive", "mood", "checkins"] as $key) {
                $this->assertArrayNotHasKey($key, $row, "the roster exposed '{$key}'");
            }

            // What it MAY carry: contract data the admin already holds.
            $this->assertArrayHasKey("id", $row);
            $this->assertArrayHasKey("status", $row);
            $this->assertArrayHasKey("department", $row);
        }
    }

    public function test_safety_reports_never_name_the_reporter_or_quote_them(): void
    {
        $reports = $this->getJson("/api/v2/business/safety-reports")->assertStatus(200)->json("data.reports");

        $this->assertCount(1, $reports);
        $report = $reports[0];

        $this->assertSame("Employee (anon.)", $report["filed_by"]);
        $this->assertSame("Late to session", $report["category"]);
        $this->assertArrayNotHasKey("reporter_id", $report);
        $this->assertArrayNotHasKey("reported_user_id", $report);
        $this->assertArrayNotHasKey("context", $report);
        $this->assertArrayNotHasKey("description", $report);
    }

    /**
     * The aggregate endpoints must be returning REAL figures here — otherwise
     * this whole file would pass trivially by returning nothing.
     */
    public function test_the_aggregates_are_actually_populated(): void
    {
        $overview = $this->getJson("/api/v2/business/insights/overview")->json("data");

        $this->assertSame(6, $overview["cohort"]);
        $this->assertFalse($overview["kpis"]["sessions_this_month"]["suppressed"]);
        $this->assertSame(6, $overview["kpis"]["sessions_this_month"]["value"]);
        $this->assertNotEmpty($overview["top_topics"]["value"]);

        $this->assertFalse(
            $this->getJson("/api/v2/business/insights/team-needs")->json("data.suppressed")
        );
    }
}
