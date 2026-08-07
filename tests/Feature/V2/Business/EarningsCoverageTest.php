<?php

namespace Tests\Feature\V2\Business;

use App\Constants\Business\OrganizationConstants as OC;
use App\Constants\Business\SessionCoverageConstants as Cov;
use App\Constants\Therapist\TherapistConstants;
use App\Models\Organization;
use App\Models\OrganizationInvoice;
use App\Models\Therapist;
use App\Models\TherapySession;
use App\Models\User;
use App\Services\Business\OrganizationBillingRunService;
use App\Services\Therapist\EarningsLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage-aware therapist credits (web §09 Phase 9.1b): consumer/prepay pay on
 * completion, postpay is HELD until the employer settles, own-therapist earns
 * nothing here. Consumer behaviour is unchanged.
 */
class EarningsCoverageTest extends TestCase
{
    use RefreshDatabase;

    private function therapist(): Therapist
    {
        return Therapist::factory()->create(['user_id' => User::factory()->create()->id]);
    }

    private function completed(Therapist $t, string $coverage, ?int $orgId = null, $startsAt = null): TherapySession
    {
        return TherapySession::create([
            'uuid' => strtoupper(uniqid()),
            'user_id' => User::factory()->create()->id,
            'therapist_id' => $t->id,
            'organization_id' => $orgId,
            'coverage' => $coverage,
            'starts_at' => $startsAt ?? now(),
            'duration_minutes' => 50, 'format' => 'video',
            'status' => TherapistConstants::SESSION_COMPLETED,
            'amount' => 15000,
        ]);
    }

    public function test_consumer_credit_is_unchanged_and_available(): void
    {
        $t = $this->therapist();
        $s = $this->completed($t, Cov::CONSUMER);
        EarningsLedgerService::creditForSession($s);

        $this->assertEqualsWithDelta(EarningsLedgerService::netFor($s), EarningsLedgerService::balance($t), 0.01);
    }

    public function test_org_bundle_credits_the_flat_network_rate_available_now(): void
    {
        config(['business.network_session_payout' => 8000]);
        $t = $this->therapist();
        $s = $this->completed($t, Cov::ORG_BUNDLE, orgId: null);
        EarningsLedgerService::creditForSession($s);

        // Flat network rate, not the ₦15,000 session amount, and available immediately.
        $this->assertEqualsWithDelta(8000, EarningsLedgerService::balance($t), 0.01);
    }

    public function test_org_external_earns_nothing_in_talkam(): void
    {
        $t = $this->therapist();
        $s = $this->completed($t, Cov::ORG_EXTERNAL);

        $this->assertNull(EarningsLedgerService::creditForSession($s));
        $this->assertSame(0.0, EarningsLedgerService::balance($t));
    }

    public function test_postpay_is_held_out_of_balance_until_the_invoice_settles(): void
    {
        config(['business.network_session_payout' => 8000]);
        $org = Organization::create([
            'name' => 'Co', 'slug' => 'co-' . uniqid(), 'domain' => 'co' . uniqid() . '.ng',
            'status' => OC::STATUS_ACTIVE, 'verified_at' => now(),
        ]);
        $t = $this->therapist();
        $s = $this->completed($t, Cov::ORG_METER, orgId: $org->id, startsAt: now());
        EarningsLedgerService::creditForSession($s);

        // Earned, but withheld: not in balance, shown as pending settlement.
        $this->assertSame(0.0, EarningsLedgerService::balance($t));
        $this->assertEqualsWithDelta(8000, EarningsLedgerService::pendingSettlement($t)['amount'], 0.01);
        $this->assertSame(1, EarningsLedgerService::pendingSettlement($t)['sessions']);

        // Settling the org's invoice for that period releases the held credit.
        $invoice = OrganizationInvoice::create([
            'organization_id' => $org->id, 'reference' => 'INV-REL-' . uniqid(),
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'seats' => 1, 'amount' => 8240,
            'status' => OrganizationInvoice::STATUS_DUE,
            'issued_at' => now(), 'due_at' => now()->addDays(14),
        ]);
        OrganizationBillingRunService::markPaid($invoice);

        $this->assertEqualsWithDelta(8000, EarningsLedgerService::balance($t), 0.01);
        $this->assertSame(0.0, EarningsLedgerService::pendingSettlement($t)['amount']);
    }

    public function test_credit_is_idempotent_regardless_of_coverage(): void
    {
        config(['business.network_session_payout' => 8000]);
        $t = $this->therapist();
        $s = $this->completed($t, Cov::ORG_BUNDLE);

        EarningsLedgerService::creditForSession($s);
        EarningsLedgerService::creditForSession($s);

        $this->assertEqualsWithDelta(8000, EarningsLedgerService::balance($t), 0.01);
    }
}
