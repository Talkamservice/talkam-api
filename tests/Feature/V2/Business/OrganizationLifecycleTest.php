<?php

namespace Tests\Feature\V2\Business;

use App\Constants\Business\OrganizationConstants as OC;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Therapist;
use App\Models\TherapistAvailability;
use App\Models\TherapySession;
use App\Models\User;
use App\Services\Business\OrganizationBillingRunService;
use App\Services\Business\OrganizationLifecycleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Admin Settings → Danger Zone (web §03): suspend, cancel subscription, delete company. */
class OrganizationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(Organization $organization): User
    {
        $user = User::factory()->create();
        OrganizationMember::factory()->admin()->create([
            "organization_id" => $organization->id,
            "user_id" => $user->id,
        ]);
        return $user;
    }

    private function memberOf(Organization $organization, string $role): User
    {
        $user = User::factory()->create();
        OrganizationMember::create([
            "organization_id" => $organization->id, "user_id" => $user->id,
            "role" => $role, "status" => OC::MEMBER_ACTIVE, "activated_at" => now(),
        ]);
        return $user;
    }

    private function bookableTherapist(): Therapist
    {
        $t = Therapist::factory()->create(["session_rate" => 17500]);
        TherapistAvailability::factory()->create([
            "user_id" => $t->user_id, "day_of_week" => "monday",
            "start_time" => "09:00", "end_time" => "18:00",
        ]);
        return $t;
    }

    private function book(User $user, Therapist $t)
    {
        Sanctum::actingAs($user);
        return $this->postJson("/api/v2/user/bookings", [
            "therapist_id" => $t->id,
            "starts_at" => Carbon::parse("next monday")->setTime(9, 0)->toDateTimeString(),
            "format" => "video",
        ]);
    }

    /* ── Suspend / resume employee access ────────────────────────────── */

    public function test_admin_can_suspend_and_resume_employee_access(): void
    {
        $org = Organization::factory()->create();
        $admin = $this->admin($org);
        Sanctum::actingAs($admin);

        $this->postJson("/api/v2/business/organization/employee-access", ["suspended" => true])
            ->assertStatus(200);
        $this->assertNotNull($org->refresh()->employees_suspended_at);

        $this->postJson("/api/v2/business/organization/employee-access", ["suspended" => false])
            ->assertStatus(200);
        $this->assertNull($org->refresh()->employees_suspended_at);
    }

    public function test_suspended_employee_is_blocked_from_business_dashboard_and_booking(): void
    {
        $org = Organization::factory()->create(["employees_suspended_at" => now()]);
        $employee = $this->memberOf($org, OC::ROLE_EMPLOYEE);
        $t = $this->bookableTherapist();

        Sanctum::actingAs($employee);
        $this->getJson("/api/v2/business/self-check")->assertStatus(403);

        $this->book($employee, $t)->assertStatus(403);
        $this->assertSame(0, TherapySession::count());
    }

    public function test_suspension_does_not_lock_out_the_admin_or_an_org_linked_therapist(): void
    {
        $org = Organization::factory()->create(["employees_suspended_at" => now()]);
        $admin = $this->admin($org);
        $therapistUser = $this->memberOf($org, OC::ROLE_THERAPIST);

        Sanctum::actingAs($admin);
        $this->getJson("/api/v2/business/organization")->assertStatus(200);

        Sanctum::actingAs($therapistUser);
        $this->getJson("/api/v2/business/onboarding/topics")->assertStatus(200);
    }

    public function test_a_consumer_with_no_organization_is_unaffected_by_any_suspension(): void
    {
        $user = User::factory()->create();
        $t = $this->bookableTherapist();

        $this->book($user, $t)->assertOk();
    }

    /* ── Cancel / resume subscription ────────────────────────────────── */

    public function test_admin_can_cancel_and_resume_the_subscription(): void
    {
        $org = Organization::factory()->create(["status" => OC::STATUS_ACTIVE]);
        $admin = $this->admin($org);
        Sanctum::actingAs($admin);

        $this->postJson("/api/v2/business/organization/cancel-subscription")->assertStatus(200);
        $org->refresh();
        $this->assertNotNull($org->cancels_at);
        $this->assertSame(OC::STATUS_ACTIVE, $org->status); // access continues until the cutoff

        $this->postJson("/api/v2/business/organization/cancel-subscription")->assertStatus(400); // already scheduled

        $this->postJson("/api/v2/business/organization/resume-subscription")->assertStatus(200);
        $this->assertNull($org->refresh()->cancels_at);
    }

    public function test_cancellation_sweep_only_lands_once_the_cutoff_has_passed(): void
    {
        $future = Organization::factory()->create(["status" => OC::STATUS_ACTIVE, "cancels_at" => now()->addDay()]);
        $due = Organization::factory()->create(["status" => OC::STATUS_ACTIVE, "cancels_at" => now()->subDay()]);

        $result = OrganizationLifecycleService::processCancellations();

        $this->assertSame(1, $result["cancelled"]);
        $this->assertSame(OC::STATUS_ACTIVE, $future->refresh()->status);
        $this->assertSame(OC::STATUS_CANCELLED, $due->refresh()->status);
    }

    public function test_a_cancelled_organization_blocks_every_role_everywhere(): void
    {
        $org = Organization::factory()->create(["status" => OC::STATUS_CANCELLED]);
        $admin = $this->admin($org);
        $employee = $this->memberOf($org, OC::ROLE_EMPLOYEE);
        $t = $this->bookableTherapist();

        Sanctum::actingAs($admin);
        $this->getJson("/api/v2/business/organization")->assertStatus(403);

        $this->book($employee, $t)->assertStatus(403);
    }

    public function test_billing_run_skips_cancelled_organizations(): void
    {
        Organization::factory()->create([
            "status" => OC::STATUS_CANCELLED, "seats_licensed" => 10, "verified_at" => now(),
        ]);

        $result = OrganizationBillingRunService::run(now()->startOfMonth(), now()->endOfMonth());

        $this->assertSame(0, $result["invoiced"]);
    }

    /* ── Delete company account ──────────────────────────────────────── */

    public function test_deletion_requires_the_exact_company_name(): void
    {
        $org = Organization::factory()->create(["name" => "Acme Co"]);
        $admin = $this->admin($org);
        Sanctum::actingAs($admin);

        $this->postJson("/api/v2/business/organization/request-deletion", ["confirm_name" => "Acme"])
            ->assertStatus(422);

        $this->assertNull($org->refresh()->scheduled_deletion_at);

        $this->postJson("/api/v2/business/organization/request-deletion", ["confirm_name" => "Acme Co"])
            ->assertStatus(200);

        $this->assertNotNull($org->refresh()->scheduled_deletion_at);
    }

    public function test_deletion_can_be_cancelled_and_access_continues_during_the_grace_period(): void
    {
        $org = Organization::factory()->create(["name" => "Acme Co", "scheduled_deletion_at" => now()->addDays(30)]);
        $admin = $this->admin($org);
        $employee = $this->memberOf($org, OC::ROLE_EMPLOYEE);
        $t = $this->bookableTherapist();

        // Still within the grace period — nobody is blocked yet.
        $this->book($employee, $t)->assertOk();

        Sanctum::actingAs($admin);
        $this->postJson("/api/v2/business/organization/cancel-deletion")->assertStatus(200);
        $this->assertNull($org->refresh()->scheduled_deletion_at);
    }

    public function test_purge_sweep_only_removes_organizations_past_their_grace_period(): void
    {
        $notYet = Organization::factory()->create(["scheduled_deletion_at" => now()->addDay()]);
        $due = Organization::factory()->create(["scheduled_deletion_at" => now()->subDay()]);
        $dueAdmin = $this->admin($due);
        $dueEmployee = $this->memberOf($due, OC::ROLE_EMPLOYEE);

        $result = OrganizationLifecycleService::purgeScheduledDeletions();

        $this->assertSame(1, $result["purged"]);
        $this->assertNotNull(Organization::find($notYet->id));
        $this->assertNull(Organization::find($due->id)); // soft-deleted, excluded from normal queries
        $this->assertNotNull(Organization::withTrashed()->find($due->id));

        $this->assertSame(
            OC::MEMBER_INACTIVE,
            OrganizationMember::where("user_id", $dueAdmin->id)->first()->status
        );
        $this->assertSame(
            OC::MEMBER_INACTIVE,
            OrganizationMember::where("user_id", $dueEmployee->id)->first()->status
        );

        // The people themselves are untouched — only their org membership is.
        $this->assertNotNull(User::find($dueAdmin->id));
        $this->assertNotNull(User::find($dueEmployee->id));
    }
}
