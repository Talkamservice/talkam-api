<?php

namespace Tests\Feature\V2\Admin;

use App\Constants\Business\OrganizationConstants;
use App\Models\EmployeeSelfCheck;
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
 * The privacy floor, exhaustively.
 *
 * "Suppress any aggregate computed from fewer than 5 employees" is the hardest
 * rule in this product, so it gets its own file: one test per surface, each
 * asserting the boundary from both sides (4 employees → withheld, 5 → shown).
 */
class SuppressionTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create(["seats_licensed" => 100]);
        $this->admin = User::factory()->create();

        OrganizationMember::factory()->admin()->create([
            "organization_id" => $this->organization->id,
            "user_id" => $this->admin->id,
        ]);

        Sanctum::actingAs($this->admin);
    }

    /** @return User[] */
    private function employees(int $count, ?string $department = "Technology"): array
    {
        $users = [];

        for ($i = 0; $i < $count; $i++) {
            $user = User::factory()->create();
            OrganizationMember::factory()->create([
                "organization_id" => $this->organization->id,
                "user_id" => $user->id,
                "role" => OrganizationConstants::ROLE_EMPLOYEE,
                "department" => $department,
            ]);
            $users[] = $user;
        }

        return $users;
    }

    private function checkIn(User $user, array $factors = ["work"]): void
    {
        MoodCheckin::create([
            "user_id" => $user->id,
            "mood" => 3,
            "checked_in_on" => now()->toDateString(),
            "factors" => $factors,
        ]);
    }

    private function bookSession(User $user): void
    {
        TherapySession::factory()->completed()->create([
            "user_id" => $user->id,
            "therapist_id" => Therapist::factory()->create()->id,
            "starts_at" => now()->startOfMonth()->addDay(),
        ]);
    }

    /* ── The floor itself ───────────────────────────────────────────────── */

    public function test_the_configured_floor_is_five(): void
    {
        $this->assertSame(5, (int) config("business.aggregate_minimum_cohort"));
    }

    /** @dataProvider cohorts */
    public function test_overview_aggregates_respect_the_floor(int $count, bool $suppressed): void
    {
        foreach ($this->employees($count) as $user) {
            $this->checkIn($user);
            $this->bookSession($user);
        }

        $data = $this->getJson("/api/v2/business/insights/overview")->assertStatus(200)->json("data");

        $this->assertSame($count, $data["cohort"]);

        foreach (["active_this_month", "sessions_this_month"] as $kpi) {
            $this->assertSame($suppressed, $data["kpis"][$kpi]["suppressed"], "{$kpi} at cohort {$count}");
            if ($suppressed) {
                $this->assertNull($data["kpis"][$kpi]["value"]);
            } else {
                $this->assertNotNull($data["kpis"][$kpi]["value"]);
            }
        }

        $this->assertSame($suppressed, $data["session_activity"]["suppressed"]);
        $this->assertSame($suppressed, $data["top_topics"]["suppressed"]);
        $this->assertSame($suppressed, $data["roi"]["suppressed"]);

        if ($suppressed) {
            $this->assertNull($data["session_activity"]["value"]);
            $this->assertNull($data["top_topics"]["value"]);
            $this->assertNull($data["roi"]["value"]);
        }
    }

    public static function cohorts(): array
    {
        return [
            "0 employees" => [0, true],
            "1 employee" => [1, true],
            "4 employees" => [4, true],
            "5 employees — the floor" => [5, false],
            "6 employees" => [6, false],
        ];
    }

    /**
     * The subtle one: a company big enough overall can still contain a
     * department small enough to identify someone.
     */
    public function test_a_small_department_is_suppressed_inside_a_large_company(): void
    {
        foreach ($this->employees(6, "Technology") as $user) {
            $this->bookSession($user);
        }
        foreach ($this->employees(1, "Legal") as $user) {
            $this->bookSession($user);
        }

        $departments = collect(
            $this->getJson("/api/v2/business/insights/overview")->json("data.departments")
        )->keyBy("department");

        $this->assertFalse($departments["Technology"]["suppressed"]);
        $this->assertSame(6, $departments["Technology"]["value"]);

        $this->assertTrue($departments["Legal"]["suppressed"], "a 1-person department must be withheld");
        $this->assertNull($departments["Legal"]["value"]);
        // The headcount is administrative and may be shown; the behaviour is not.
        $this->assertSame(1, $departments["Legal"]["members"]);
    }

    /** Team needs count RESPONDENTS, not members. */
    public function test_team_needs_are_suppressed_until_five_people_respond(): void
    {
        $users = $this->employees(8);

        foreach (array_slice($users, 0, 4) as $user) {
            EmployeeSelfCheck::create([
                "organization_id" => $this->organization->id,
                "user_id" => $user->id,
                "category" => OrganizationConstants::SELF_CHECK_WORK,
                "score" => 3,
                "answered_at" => now(),
            ]);
        }

        $this->getJson("/api/v2/business/insights/team-needs")
            ->assertStatus(200)
            ->assertJsonPath("data.suppressed", true)
            ->assertJsonPath("data.value", null)
            ->assertJsonPath("data.cohort", 4);

        EmployeeSelfCheck::create([
            "organization_id" => $this->organization->id,
            "user_id" => $users[4]->id,
            "category" => OrganizationConstants::SELF_CHECK_WORK,
            "score" => 3,
            "answered_at" => now(),
        ]);

        $this->getJson("/api/v2/business/insights/team-needs")
            ->assertStatus(200)
            ->assertJsonPath("data.suppressed", false)
            ->assertJsonPath("data.cohort", 5)
            ->assertJsonPath("data.value.0.label", "Work Stress");
    }

    public function test_monthly_trend_and_therapist_stats_respect_the_floor(): void
    {
        foreach ($this->employees(4) as $user) {
            $this->bookSession($user);
        }

        $this->getJson("/api/v2/business/reports")
            ->assertStatus(200)
            ->assertJsonPath("data.monthly_trend.suppressed", true)
            ->assertJsonPath("data.monthly_trend.value", null);

        $this->getJson("/api/v2/business/therapists")
            ->assertStatus(200)
            ->assertJsonPath("data.stats.suppressed", true)
            ->assertJsonPath("data.stats.sessions_used", null);

        // Seat counts are administrative, not behavioural — always shown.
        $this->assertSame(100, $this->getJson("/api/v2/business/therapists")->json("data.stats.seats_total"));

        $this->employees(1);

        $this->getJson("/api/v2/business/reports")
            ->assertStatus(200)
            ->assertJsonPath("data.monthly_trend.suppressed", false);
    }

    /** A suppressed CSV must contain the explanation, not the numbers. */
    public function test_report_downloads_are_suppressed_too(): void
    {
        foreach ($this->employees(3) as $user) {
            $this->bookSession($user);
        }

        foreach (["usage", "wellness", "roi"] as $key) {
            $csv = $this->get("/api/v2/business/reports/{$key}/download")
                ->assertStatus(200)
                ->streamedContent();

            $this->assertStringContainsString("Not enough data", $csv, "{$key} report leaked figures");
        }
    }

    public function test_an_unknown_report_key_is_not_found(): void
    {
        $this->employees(6);

        $this->getJson("/api/v2/business/reports/salaries/download")->assertStatus(404);
    }

    /** Above the floor, the numbers must actually be right. */
    public function test_above_the_floor_the_figures_are_correct(): void
    {
        $users = $this->employees(5);

        foreach ($users as $user) {
            $this->checkIn($user, ["work", "sleep"]);
        }
        // Three of the five had a session this month.
        foreach (array_slice($users, 0, 3) as $user) {
            $this->bookSession($user);
        }

        $data = $this->getJson("/api/v2/business/insights/overview")->json("data");

        $this->assertSame(5, $data["kpis"]["active_this_month"]["value"]);
        $this->assertEquals(100.0, $data["kpis"]["active_this_month"]["rate"]);
        $this->assertSame(3, $data["kpis"]["sessions_this_month"]["value"]);
        $this->assertSame(3, $data["roi"]["value"]["sessions"]);

        $topics = collect($data["top_topics"]["value"])->keyBy("key");
        $this->assertSame(50, $topics["work"]["percent"]);
        $this->assertSame(50, $topics["sleep"]["percent"]);
    }
}
