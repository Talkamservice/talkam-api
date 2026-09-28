<?php

namespace Tests\Feature\V2\Business;

use App\Constants\Business\OrganizationConstants as OC;
use App\Constants\Business\SessionCoverageConstants as Cov;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Therapist;
use App\Models\User;
use App\Services\Business\CoverageResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * How a booking is covered (web §09 Phase 9.1b). Feature-flagged — with coverage
 * OFF everything is a consumer session (today's behaviour).
 */
class CoverageResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['business.coverage_enabled' => true]);
    }

    private function org(array $o = []): Organization
    {
        return Organization::create(array_merge([
            'name' => 'Co', 'slug' => 'co-' . uniqid(), 'domain' => 'co' . uniqid() . '.ng',
            'status' => OC::STATUS_ACTIVE, 'therapist_access' => true,
            'payment_timing' => 'prepay', 'session_bundle_sessions' => 25,
            'verified_at' => now(),
        ], $o));
    }

    private function employee(Organization $org): User
    {
        $u = User::factory()->create();
        OrganizationMember::create([
            'organization_id' => $org->id, 'user_id' => $u->id,
            'role' => OC::ROLE_EMPLOYEE, 'status' => OC::MEMBER_ACTIVE, 'activated_at' => now(),
        ]);
        return $u;
    }

    private function networkTherapist(): Therapist
    {
        return Therapist::factory()->create(['user_id' => User::factory()->create()->id]);
    }

    public function test_flag_off_is_always_consumer(): void
    {
        config(['business.coverage_enabled' => false]);
        $org = $this->org();
        $r = CoverageResolver::resolve($this->employee($org), $this->networkTherapist());
        $this->assertSame(Cov::CONSUMER, $r['coverage']);
    }

    public function test_non_employee_is_consumer(): void
    {
        $r = CoverageResolver::resolve(User::factory()->create(), $this->networkTherapist());
        $this->assertSame(Cov::CONSUMER, $r['coverage']);
    }

    public function test_therapist_access_off_is_consumer(): void
    {
        $org = $this->org(['therapist_access' => false]);
        $r = CoverageResolver::resolve($this->employee($org), $this->networkTherapist());
        $this->assertSame(Cov::CONSUMER, $r['coverage']);
    }

    public function test_prepay_with_bundle_is_org_bundle_paid_on_completion(): void
    {
        $org = $this->org(['payment_timing' => 'prepay', 'session_bundle_sessions' => 25]);
        $r = CoverageResolver::resolve($this->employee($org), $this->networkTherapist());
        $this->assertSame(Cov::ORG_BUNDLE, $r['coverage']);
        $this->assertSame(8000, $r['billed_amount']);
        $this->assertSame(Cov::PAYOUT_ON_COMPLETION, $r['payout_timing']);
    }

    public function test_postpay_is_org_meter_paid_on_settlement(): void
    {
        $org = $this->org(['payment_timing' => 'postpay', 'session_bundle_sessions' => 0]);
        $r = CoverageResolver::resolve($this->employee($org), $this->networkTherapist());
        $this->assertSame(Cov::ORG_METER, $r['coverage']);
        $this->assertSame(8240, $r['billed_amount']);
        $this->assertSame(Cov::PAYOUT_ON_SETTLEMENT, $r['payout_timing']);
    }

    public function test_own_therapist_is_external(): void
    {
        $org = $this->org();
        $therapistUser = User::factory()->create();
        OrganizationMember::create([
            'organization_id' => $org->id, 'user_id' => $therapistUser->id,
            'role' => OC::ROLE_THERAPIST, 'status' => OC::MEMBER_ACTIVE, 'activated_at' => now(),
        ]);
        $own = Therapist::factory()->create(['user_id' => $therapistUser->id]);

        $r = CoverageResolver::resolve($this->employee($org), $own);
        $this->assertSame(Cov::ORG_EXTERNAL, $r['coverage']);
        $this->assertSame(Cov::PAYOUT_EXTERNAL, $r['payout_timing']);
    }

    public function test_prepay_exhausted_block_policy_blocks(): void
    {
        $org = $this->org([
            'session_bundle_sessions' => 10, 'session_bundle_used' => 10,
            'bundle_exhausted_policy' => 'block',
        ]);
        $r = CoverageResolver::resolve($this->employee($org), $this->networkTherapist());
        $this->assertSame(Cov::BLOCKED, $r['coverage']);
        $this->assertNotNull($r['blocked_reason']);
    }

    public function test_prepay_exhausted_auto_meter_falls_through_to_meter(): void
    {
        $org = $this->org([
            'session_bundle_sessions' => 10, 'session_bundle_used' => 10,
            'bundle_exhausted_policy' => 'auto_meter',
        ]);
        $r = CoverageResolver::resolve($this->employee($org), $this->networkTherapist());
        $this->assertSame(Cov::ORG_METER, $r['coverage']);
    }
}
