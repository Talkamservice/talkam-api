<?php

namespace Tests\Feature\V2\Business;

use App\Constants\Business\OrganizationConstants as OC;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Therapist;
use App\Models\TherapistAvailability;
use App\Models\TherapySession;
use App\Models\User;
use App\Notifications\Business\SessionCapRequestNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The "max sessions per employee, per billing cycle" policy (web §03
 * Settings → Session Policy): admin persistence, booking-time enforcement,
 * and the employee's "notify my admin" action. Independent of
 * business.coverage_enabled — see BookingCoverageTest for that flag.
 */
class SessionCapTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $org = []): array
    {
        $organization = Organization::factory()->create($org);
        $user = User::factory()->create();

        OrganizationMember::factory()->admin()->create([
            "organization_id" => $organization->id,
            "user_id" => $user->id,
        ]);

        Sanctum::actingAs($user);

        return [$organization, $user];
    }

    private function employeeOf(Organization $org): User
    {
        $u = User::factory()->create();
        OrganizationMember::create([
            "organization_id" => $org->id, "user_id" => $u->id,
            "role" => OC::ROLE_EMPLOYEE, "status" => OC::MEMBER_ACTIVE, "activated_at" => now(),
        ]);
        return $u;
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

    private $slotHour = 9;

    /** A fresh Monday slot each call, so back-to-back bookings never clash. */
    private function nextSlot(): string
    {
        return Carbon::parse("next monday")->setTime($this->slotHour++, 0)->toDateTimeString();
    }

    private function book(User $user, Therapist $t)
    {
        Sanctum::actingAs($user);
        return $this->postJson("/api/v2/user/bookings", [
            "therapist_id" => $t->id, "starts_at" => $this->nextSlot(), "format" => "video",
        ]);
    }

    /* ── Admin persistence ────────────────────────────────────────────── */

    public function test_admin_can_enable_the_cap_with_a_quota(): void
    {
        [$organization] = $this->admin();

        $this->postJson("/api/v2/business/organization/session-policy", [
            "cap_enabled" => true,
            "per_employee_session_quota" => 6,
        ])
            ->assertStatus(200)
            ->assertJsonPath("data.organization.per_employee_session_quota", 6);

        $this->assertSame(6, (int) $organization->refresh()->per_employee_session_quota);
    }

    public function test_disabling_the_cap_clears_the_quota_to_null(): void
    {
        [$organization] = $this->admin(["per_employee_session_quota" => 6]);

        $this->postJson("/api/v2/business/organization/session-policy", [
            "cap_enabled" => false,
        ])
            ->assertStatus(200)
            ->assertJsonPath("data.organization.per_employee_session_quota", null);

        $this->assertNull($organization->refresh()->per_employee_session_quota);
    }

    public function test_enabling_without_a_quota_is_rejected(): void
    {
        [$organization] = $this->admin();

        $this->postJson("/api/v2/business/organization/session-policy", [
            "cap_enabled" => true,
        ])->assertStatus(422);

        $this->assertNull($organization->refresh()->per_employee_session_quota);
    }

    public function test_zero_or_negative_quota_is_rejected(): void
    {
        $this->admin();

        foreach ([0, -3] as $quota) {
            $this->postJson("/api/v2/business/organization/session-policy", [
                "cap_enabled" => true,
                "per_employee_session_quota" => $quota,
            ])->assertStatus(422);
        }
    }

    /* ── Booking-time enforcement ─────────────────────────────────────── */

    public function test_employee_can_book_up_to_the_cap_then_is_blocked(): void
    {
        $org = Organization::factory()->create(["per_employee_session_quota" => 2]);
        $emp = $this->employeeOf($org);
        $t = $this->bookableTherapist();

        $this->book($emp, $t)->assertOk();
        $this->book($emp, $t)->assertOk();

        $response = $this->book($emp, $t)->assertStatus(400);
        $this->assertStringContainsString("2 of your 2 sessions", $response->json("message"));

        $this->assertSame(2, TherapySession::count());
    }

    public function test_uncapped_org_never_blocks_booking(): void
    {
        $org = Organization::factory()->create(["per_employee_session_quota" => null]);
        $emp = $this->employeeOf($org);
        $t = $this->bookableTherapist();

        for ($i = 0; $i < 4; $i++) {
            $this->book($emp, $t)->assertOk();
        }

        $this->assertSame(4, TherapySession::count());
    }

    public function test_cap_applies_regardless_of_the_coverage_flag(): void
    {
        config(["business.coverage_enabled" => false]);
        $org = Organization::factory()->create(["per_employee_session_quota" => 1]);
        $emp = $this->employeeOf($org);
        $t = $this->bookableTherapist();

        $this->book($emp, $t)->assertOk();
        $this->assertSame("consumer", TherapySession::first()->coverage);

        $this->book($emp, $t)->assertStatus(400);
        $this->assertSame(1, TherapySession::count());
    }

    public function test_a_consumer_with_no_organization_is_never_capped(): void
    {
        $user = User::factory()->create();
        $t = $this->bookableTherapist();

        for ($i = 0; $i < 3; $i++) {
            $this->book($user, $t)->assertOk();
        }

        $this->assertSame(3, TherapySession::count());
    }

    public function test_only_sessions_booked_in_the_current_billing_cycle_count(): void
    {
        $org = Organization::factory()->create(["per_employee_session_quota" => 1]);
        $emp = $this->employeeOf($org);
        $t = $this->bookableTherapist();

        // Booked last billing cycle (even for a still-upcoming slot) — shouldn't
        // count against this cycle's cap.
        $old = TherapySession::create([
            "uuid" => "OLD1", "user_id" => $emp->id, "therapist_id" => $t->id,
            "starts_at" => now()->addWeek(), "duration_minutes" => 50, "format" => "video",
            "status" => "confirmed", "amount" => 17500, "currency" => "NGN",
        ]);
        $old->timestamps = false;
        $old->created_at = now()->subMonth();
        $old->save();

        $this->book($emp, $t)->assertOk();
        $this->assertSame(2, TherapySession::count());
    }

    /* ── Notify-admin ─────────────────────────────────────────────────── */

    public function test_request_top_up_notifies_active_admins_of_the_org(): void
    {
        Notification::fake();
        $org = Organization::factory()->create(["per_employee_session_quota" => 2]);
        $emp = $this->employeeOf($org);
        $adminUser = User::factory()->create();
        OrganizationMember::factory()->admin()->create([
            "organization_id" => $org->id, "user_id" => $adminUser->id,
        ]);

        Sanctum::actingAs($emp);
        $this->postJson("/api/v2/user/bookings/request-top-up")->assertStatus(200);

        Notification::assertSentTo($adminUser, SessionCapRequestNotification::class);
    }

    public function test_request_top_up_rejects_a_user_with_no_organization(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/user/bookings/request-top-up")->assertStatus(400);
    }

    public function test_bookings_summary_exposes_the_employee_cap(): void
    {
        $org = Organization::factory()->create(["per_employee_session_quota" => 3]);
        $emp = $this->employeeOf($org);
        $t = $this->bookableTherapist();

        $this->book($emp, $t)->assertOk();

        Sanctum::actingAs($emp);
        $this->getJson("/api/v2/user/bookings")
            ->assertStatus(200)
            ->assertJsonPath("data.summary.employee_cap", 3)
            ->assertJsonPath("data.summary.employee_cap_used", 1);
    }
}
