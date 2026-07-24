<?php

namespace Tests\Feature\V2\Business;

use App\Constants\Business\OrganizationConstants;
use App\Models\Organization;
use App\Models\OrganizationInvoice;
use App\Models\OrganizationMember;
use App\Models\Therapist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The admin Billing screen (web §07): current plan, usage, catalogue and
 * invoices. Admin-gated and tenant-scoped.
 */
class BillingTest extends TestCase
{
    use RefreshDatabase;

    private function orgWithAdmin(array $orgOverrides = []): array
    {
        $org = Organization::create(array_merge([
            "name" => "Meridian Health",
            "slug" => "meridian-" . uniqid(),
            "domain" => "meridian" . uniqid() . ".ng",
            "status" => OrganizationConstants::STATUS_ACTIVE,
            "seats_licensed" => 50,
            "therapist_access" => true,
            "session_bundle_sessions" => 25,
            "verified_at" => now(),
        ], $orgOverrides));

        $admin = User::factory()->create();
        OrganizationMember::create([
            "organization_id" => $org->id,
            "user_id" => $admin->id,
            "role" => OrganizationConstants::ROLE_ADMIN,
            "status" => OrganizationConstants::MEMBER_ACTIVE,
            "activated_at" => now(),
        ]);

        return [$org, $admin];
    }

    private function member(Organization $org, string $role): User
    {
        $user = User::factory()->create();
        OrganizationMember::create([
            "organization_id" => $org->id,
            "user_id" => $user->id,
            "role" => $role,
            "status" => OrganizationConstants::MEMBER_ACTIVE,
            "activated_at" => now(),
        ]);

        return $user;
    }

    /* ── Summary ────────────────────────────────────────────────────────── */

    public function test_summary_returns_the_plan_line_items_usage_and_catalogue(): void
    {
        [$org, $admin] = $this->orgWithAdmin();
        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/v2/business/billing");

        $response->assertOk();
        $data = $response->json("data");

        // Current plan: employee seats + therapist access + session bundle.
        $cp = $data["current_plan"];
        $this->assertStringContainsString("ACTIVE", $cp["label"]);
        $this->assertCount(3, $cp["lines"]); // seats, access, bundle
        $this->assertSame("₦100,000", $cp["lines"][0]["value"]); // 50 × 2,000
        $this->assertSame("₦175,000", $cp["lines"][1]["value"]); // 50 × 3,500
        $this->assertSame("₦200,000", $cp["lines"][2]["value"]); // 25 × 8,000
        $this->assertSame("₦475,000", $cp["total"]);

        // Usage + seats.
        $this->assertSame(50, $data["usage"]["seatsTotal"]);
        $this->assertSame(25, $data["usage"]["sessionsBundle"]);
        $this->assertSame(50, $data["current_seats"]);

        // Catalogue: the org sits in the lite band (50 seats).
        $plans = $data["catalogue"]["plans"];
        $this->assertTrue($plans["lite"]["isCurrent"]);
        $this->assertFalse($plans["core"]["isCurrent"]);
        $this->assertCount(3, $data["catalogue"]["topUpOptions"]);
        $this->assertCount(3, $data["catalogue"]["seatPackOptions"]);
    }

    public function test_current_plan_flag_follows_the_seat_band(): void
    {
        [$org, $admin] = $this->orgWithAdmin(["seats_licensed" => 800]);
        Sanctum::actingAs($admin);

        $plans = $this->getJson("/api/v2/business/billing")->json("data.catalogue.plans");

        $this->assertTrue($plans["core"]["isCurrent"]); // 501–2,000 band
        $this->assertFalse($plans["lite"]["isCurrent"]);
    }

    public function test_plan_omits_therapist_access_line_when_disabled(): void
    {
        [$org, $admin] = $this->orgWithAdmin([
            "therapist_access" => false,
            "session_bundle_sessions" => 0,
        ]);
        Sanctum::actingAs($admin);

        $cp = $this->getJson("/api/v2/business/billing")->json("data.current_plan");

        // Only the employee-seats line remains.
        $this->assertCount(1, $cp["lines"]);
        $this->assertStringContainsString("Employee Seats", $cp["lines"][0]["label"]);
    }

    /* ── Invoices ───────────────────────────────────────────────────────── */

    public function test_invoices_returns_only_the_callers_org(): void
    {
        [$org, $admin] = $this->orgWithAdmin();
        [$other] = $this->orgWithAdmin();

        OrganizationInvoice::create([
            "organization_id" => $org->id,
            "reference" => "INV-MINE",
            "period_start" => "2026-07-01",
            "period_end" => "2026-07-31",
            "seats" => 50,
            "amount" => 475000,
            "status" => "due",
            "due_at" => "2026-08-14",
        ]);
        OrganizationInvoice::create([
            "organization_id" => $other->id,
            "reference" => "INV-THEIRS",
            "period_start" => "2026-07-01",
            "period_end" => "2026-07-31",
            "seats" => 10,
            "amount" => 50000,
            "status" => "paid",
        ]);

        Sanctum::actingAs($admin);
        $data = $this->getJson("/api/v2/business/billing/invoices")->json("data");

        $refs = collect($data)->pluck("id")->all();
        $this->assertContains("INV-MINE", $refs);
        $this->assertNotContains("INV-THEIRS", $refs);
        $this->assertSame("₦475,000", $data[0]["amount"]);
        $this->assertSame("gold", $data[0]["tone"]); // due → gold
    }

    /* ── Authorization ──────────────────────────────────────────────────── */

    public function test_employee_cannot_access_billing(): void
    {
        [$org] = $this->orgWithAdmin();
        $employee = $this->member($org, OrganizationConstants::ROLE_EMPLOYEE);
        Sanctum::actingAs($employee);

        $this->getJson("/api/v2/business/billing")->assertForbidden();
        $this->getJson("/api/v2/business/billing/invoices")->assertForbidden();
    }

    public function test_therapist_member_cannot_access_billing(): void
    {
        [$org] = $this->orgWithAdmin();
        $therapistMember = $this->member($org, OrganizationConstants::ROLE_THERAPIST);
        Therapist::factory()->create(["user_id" => $therapistMember->id]);
        Sanctum::actingAs($therapistMember);

        $this->getJson("/api/v2/business/billing")->assertForbidden();
    }

    public function test_guest_is_unauthorized(): void
    {
        $this->getJson("/api/v2/business/billing")->assertUnauthorized();
    }
}
