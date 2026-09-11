<?php

namespace Tests\Feature\V2\Business;

use App\Constants\Business\OrganizationConstants as OC;
use App\Constants\Business\SessionCoverageConstants as Cov;
use App\Constants\Therapist\TherapistConstants;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OrganizationTherapist;
use App\Models\Therapist;
use App\Models\TherapistAvailability;
use App\Models\TherapySession;
use App\Models\User;
use App\Services\Business\BundleLedgerService;
use App\Services\Therapist\SessionLifecycleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The org-covered booking branch (web §09 Phase 9.1b-live). Feature-flagged: with
 * coverage OFF an employee still books a plain consumer session (unchanged).
 */
class BookingCoverageTest extends TestCase
{
    use RefreshDatabase;

    private function bookableTherapist(): Therapist
    {
        $t = Therapist::factory()->create(['session_rate' => 17500]);
        TherapistAvailability::factory()->create([
            'user_id' => $t->user_id, 'day_of_week' => 'monday',
            'start_time' => '09:00', 'end_time' => '12:00',
        ]);
        return $t;
    }

    private function slotTime(): string
    {
        return Carbon::parse('next monday')->setTime(10, 0)->toDateTimeString();
    }

    private function org(array $o = []): Organization
    {
        return Organization::create(array_merge([
            'name' => 'Co', 'slug' => 'co-' . uniqid(), 'domain' => 'co' . uniqid() . '.ng',
            'status' => OC::STATUS_ACTIVE, 'therapist_access' => true,
            'payment_timing' => 'prepay', 'session_bundle_sessions' => 25,
            'session_bundle_funded_at' => now(), // a prepay org using its bundle has paid for it
            'verified_at' => now(),
        ], $o));
    }

    private function employeeOf(Organization $org): User
    {
        $u = User::factory()->create();
        OrganizationMember::create([
            'organization_id' => $org->id, 'user_id' => $u->id,
            'role' => OC::ROLE_EMPLOYEE, 'status' => OC::MEMBER_ACTIVE, 'activated_at' => now(),
        ]);
        return $u;
    }

    private function book(User $user, Therapist $t)
    {
        Sanctum::actingAs($user);
        return $this->postJson('/api/v2/user/bookings', [
            'therapist_id' => $t->id, 'starts_at' => $this->slotTime(), 'format' => 'video',
        ]);
    }

    public function test_flag_off_employee_still_books_a_consumer_session(): void
    {
        config(['business.coverage_enabled' => false]);
        $emp = $this->employeeOf($this->org());

        $this->book($emp, $this->bookableTherapist())->assertOk();

        $s = TherapySession::first();
        $this->assertSame(Cov::CONSUMER, $s->coverage);
        $this->assertNull($s->organization_id);
        $this->assertSame(TherapistConstants::SESSION_PENDING_PAYMENT, $s->status);
        $this->assertNotNull($s->hold_expires_at);
    }

    public function test_prepay_employee_books_org_bundle_pending_review_and_draws(): void
    {
        config(['business.coverage_enabled' => true]);
        $org = $this->org(['payment_timing' => 'prepay', 'session_bundle_sessions' => 25]);
        $emp = $this->employeeOf($org);

        $this->book($emp, $this->bookableTherapist())->assertOk();

        $s = TherapySession::first();
        $this->assertSame(Cov::ORG_BUNDLE, $s->coverage);
        $this->assertSame($org->id, $s->organization_id);
        // Nothing to pay, but the therapist still reviews it before it's real.
        $this->assertSame(TherapistConstants::SESSION_PENDING_PAYMENT, $s->status);
        $this->assertNull($s->hold_expires_at);      // employer-funded: no payment hold
        $this->assertNull($s->payment_id);           // no client charge
        $this->assertSame(24, BundleLedgerService::remaining($org->refresh())); // reserved on booking, not on accept
    }

    public function test_postpay_employee_books_org_meter_pending_review(): void
    {
        config(['business.coverage_enabled' => true]);
        $org = $this->org(['payment_timing' => 'postpay', 'session_bundle_sessions' => 0]);
        $emp = $this->employeeOf($org);

        $this->book($emp, $this->bookableTherapist())->assertOk();

        $s = TherapySession::first();
        $this->assertSame(Cov::ORG_METER, $s->coverage);
        $this->assertSame(TherapistConstants::SESSION_PENDING_PAYMENT, $s->status);
    }

    /** Acknowledging an org-covered pending session IS the accept — nothing
     *  to pay, so it confirms right there. A consumer one stays pending. */
    public function test_therapist_acknowledge_confirms_org_covered_session_only(): void
    {
        config(['business.coverage_enabled' => true]);
        $org = $this->org(['payment_timing' => 'prepay', 'session_bundle_sessions' => 25]);
        $emp = $this->employeeOf($org);
        $t = $this->bookableTherapist();

        $this->book($emp, $t)->assertOk();
        $session = TherapySession::first();

        Sanctum::actingAs($t->user);
        $this->postJson("/api/v2/therapist/sessions/{$session->id}/acknowledge")
            ->assertOk()
            ->assertJsonPath("data.status", TherapistConstants::SESSION_CONFIRMED);

        $this->assertSame(TherapistConstants::SESSION_CONFIRMED, $session->refresh()->status);
    }

    public function test_therapist_acknowledge_does_not_confirm_consumer_session(): void
    {
        config(['business.coverage_enabled' => false]);
        $t = $this->bookableTherapist();
        $consumer = User::factory()->create();
        Sanctum::actingAs($consumer);
        $this->postJson('/api/v2/user/bookings', [
            'therapist_id' => $t->id, 'starts_at' => $this->slotTime(), 'format' => 'video',
        ])->assertOk();
        $session = TherapySession::first();

        Sanctum::actingAs($t->user);
        $this->postJson("/api/v2/therapist/sessions/{$session->id}/acknowledge")
            ->assertOk()
            ->assertJsonPath("data.status", TherapistConstants::SESSION_PENDING_PAYMENT);

        $this->assertSame(TherapistConstants::SESSION_PENDING_PAYMENT, $session->refresh()->status);
    }

    public function test_own_therapist_books_external(): void
    {
        config(['business.coverage_enabled' => true]);
        $org = $this->org();
        $emp = $this->employeeOf($org);
        $t = $this->bookableTherapist();
        OrganizationMember::create([
            'organization_id' => $org->id, 'user_id' => $t->user_id,
            'role' => OC::ROLE_THERAPIST, 'status' => OC::MEMBER_ACTIVE, 'activated_at' => now(),
        ]);

        $this->book($emp, $t)->assertOk();
        $this->assertSame(Cov::ORG_EXTERNAL, TherapySession::first()->coverage);
    }

    /**
     * The realistic shape of an org's own therapist: brought in directly, so
     * never ran through TalkAM's consumer verification/pricing — unverified,
     * no session_rate. Both getById() (must resolve them at all, not just
     * for listing) and amount's NOT NULL fallback are exercised here.
     */
    public function test_own_therapist_with_no_session_rate_books_external(): void
    {
        config(['business.coverage_enabled' => true]);
        $org = $this->org();
        $emp = $this->employeeOf($org);

        $unpriced = Therapist::factory()->create(['session_rate' => null, 'verified_at' => null]);
        TherapistAvailability::factory()->create([
            'user_id' => $unpriced->user_id, 'day_of_week' => 'monday',
            'start_time' => '09:00', 'end_time' => '12:00',
        ]);
        OrganizationMember::create([
            'organization_id' => $org->id, 'user_id' => $unpriced->user_id,
            'role' => OC::ROLE_THERAPIST, 'status' => OC::MEMBER_ACTIVE, 'activated_at' => now(),
        ]);

        $this->book($emp, $unpriced)->assertOk();
        $session = TherapySession::first();
        $this->assertSame(Cov::ORG_EXTERNAL, $session->coverage);
        $this->assertNotNull($session->amount);
    }

    /** Same "unverified, no rate" shape, but network-added rather than own. */
    public function test_network_added_unverified_therapist_is_bookable(): void
    {
        config(['business.coverage_enabled' => true]);
        $org = $this->org();
        $emp = $this->employeeOf($org);

        $networked = Therapist::factory()->create(['session_rate' => null, 'verified_at' => null]);
        TherapistAvailability::factory()->create([
            'user_id' => $networked->user_id, 'day_of_week' => 'monday',
            'start_time' => '09:00', 'end_time' => '12:00',
        ]);
        OrganizationTherapist::create([
            'organization_id' => $org->id, 'therapist_id' => $networked->id,
            'status' => OC::NETWORK_ACTIVE,
        ]);

        Sanctum::actingAs($emp);
        $this->getJson("/api/v2/user/therapists/{$networked->id}")->assertOk();
        $this->book($emp, $networked)->assertOk();
        $this->assertNotNull(TherapySession::first()->amount);
    }

    public function test_exhausted_bundle_under_block_policy_rejects_the_booking(): void
    {
        config(['business.coverage_enabled' => true]);
        $org = $this->org([
            'session_bundle_sessions' => 5, 'session_bundle_used' => 5,
            'bundle_exhausted_policy' => 'block',
        ]);
        $emp = $this->employeeOf($org);

        $this->book($emp, $this->bookableTherapist())->assertStatus(422);
        $this->assertSame(0, TherapySession::count());
    }

    public function test_cancelling_a_bundle_session_returns_it_to_the_company(): void
    {
        Notification::fake();
        config(['business.coverage_enabled' => true]);
        $org = $this->org(['payment_timing' => 'prepay', 'session_bundle_sessions' => 25]);
        $emp = $this->employeeOf($org);

        $this->book($emp, $this->bookableTherapist())->assertOk();
        $this->assertSame(24, BundleLedgerService::remaining($org->refresh()));

        (new SessionLifecycleService)->cancel($emp, TherapySession::first()->id, []);

        $this->assertSame(25, BundleLedgerService::remaining($org->refresh()));
    }
}
