<?php

namespace Tests\Feature\V2\Business;

use App\Constants\Business\OrganizationConstants as OC;
use App\Models\Organization;
use App\Models\OrganizationBundleEntry;
use App\Models\Therapist;
use App\Models\TherapySession;
use App\Models\User;
use App\Services\Business\BundleLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The prepaid session-bundle ledger (web §09 Phase 9.1a). Pure, idempotent
 * accounting — no booking behaviour yet.
 */
class BundleLedgerTest extends TestCase
{
    use RefreshDatabase;

    private function org(int $purchased, int $used = 0): Organization
    {
        return Organization::create([
            "name" => "Co",
            "slug" => "co-" . uniqid(),
            "domain" => "co" . uniqid() . ".ng",
            "status" => OC::STATUS_ACTIVE,
            "session_bundle_sessions" => $purchased,
            "session_bundle_used" => $used,
            "session_bundle_funded_at" => now(), // a purchased bundle is a paid one
            "verified_at" => now(),
        ]);
    }

    private function makeSession(): TherapySession
    {
        $therapist = Therapist::factory()->create(["user_id" => User::factory()->create()->id]);

        return TherapySession::create([
            "uuid" => strtoupper(uniqid()),
            "user_id" => User::factory()->create()->id,
            "therapist_id" => $therapist->id,
            "starts_at" => now(),
            "duration_minutes" => 50,
            "format" => "video",
            "status" => "completed",
            "amount" => 8000,
        ]);
    }

    public function test_remaining_is_purchased_minus_used(): void
    {
        $this->assertSame(15, BundleLedgerService::remaining($this->org(25, 10)));
    }

    public function test_draw_decrements_remaining_and_is_idempotent(): void
    {
        $org = $this->org(25);
        $session = $this->makeSession();

        BundleLedgerService::draw($org, $session);
        $this->assertSame(24, BundleLedgerService::remaining($org->refresh()));

        // The same session never draws twice (retried booking).
        BundleLedgerService::draw($org, $session);
        $this->assertSame(24, BundleLedgerService::remaining($org->refresh()));
        $this->assertSame(1, OrganizationBundleEntry::where("reason", "draw")->count());
    }

    public function test_refund_restores_remaining_and_is_idempotent(): void
    {
        $org = $this->org(25);
        $session = $this->makeSession();

        BundleLedgerService::draw($org, $session);
        BundleLedgerService::refund($org, $session);
        $this->assertSame(25, BundleLedgerService::remaining($org->refresh()));

        // A second refund is a no-op.
        $this->assertNull(BundleLedgerService::refund($org->refresh(), $session));
        $this->assertSame(25, BundleLedgerService::remaining($org->refresh()));
    }

    public function test_refund_is_a_noop_when_nothing_was_drawn(): void
    {
        $org = $this->org(25);

        $this->assertNull(BundleLedgerService::refund($org, $this->makeSession()));
        $this->assertSame(25, BundleLedgerService::remaining($org->refresh()));
    }
}
