<?php

namespace Tests\Feature\V2\Business;

use App\Constants\General\StatusConstants;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Therapist;
use App\Models\User;
use App\Services\Business\OrganizationPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PricingQuoteTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): array
    {
        $organization = Organization::factory()->create(["seats_licensed" => 0]);
        $user = User::factory()->create();

        OrganizationMember::factory()->admin()->create([
            "organization_id" => $organization->id,
            "user_id" => $user->id,
        ]);

        Sanctum::actingAs($user);

        return [$organization, $user];
    }

    public function test_pricing_config_is_public_and_carries_the_rate_contract(): void
    {
        $this->getJson("/api/v2/business/pricing-config")
            ->assertStatus(200)
            ->assertJsonPath("data.session_rate", 8000)         // block rate
            ->assertJsonPath("data.session_custom_rate", 8240)  // +3% custom / metered
            ->assertJsonCount(2, "data.payment_timings")        // prepay, postpay
            ->assertJsonCount(4, "data.seat_tiers")
            ->assertJsonCount(3, "data.bundle_options")
            ->assertJsonCount(6, "data.plan.features");
    }

    /** @dataProvider tierBoundaries */
    public function test_tier_boundaries($seats, $expected_price): void
    {
        $this->assertSame($expected_price, OrganizationPricingService::tier($seats)["price"]);
    }

    public static function tierBoundaries(): array
    {
        return [
            "1 seat" => [1, 7000],
            "100 seats" => [100, 7000],
            "101 seats" => [101, 6000],
            "300 seats" => [300, 6000],
            "301 seats" => [301, 5500],
            "500 seats" => [500, 5500],
            "501 seats" => [501, 5000],
            "5000 seats" => [5000, 5000],
        ];
    }

    public function test_quote_totals_use_the_tier_seat_rate_and_bundle(): void
    {
        [$organization] = $this->admin();

        $response = $this->postJson("/api/v2/business/organization/seats", [
            "seats_licensed" => 250,
            "therapist_access" => true,
            "payment_timing" => "prepay",
            "bundle_sessions" => 25,
        ])->assertStatus(200);

        $quote = $response->json("data.quote");

        // Seats price at the volume tier (250 → ₦6,000), not a flat rate.
        $this->assertSame(6000, $quote["tier"]["price"]);
        $this->assertSame(1500000, $quote["seats_monthly"]);   // 250 × 6,000
        // Prepaid bundle at the block rate — a one-off, due now.
        $this->assertSame(200000, $quote["bundle_total"]);     // 25 × 8,000
        $this->assertSame(200000, $quote["due_now"]);
        // Recurring monthly total is seats only (the bundle is one-off).
        $this->assertSame(1500000, $quote["total_monthly"]);
        $this->assertSame("prepay", $quote["payment_timing"]);

        $organization->refresh();
        $this->assertSame(250, (int) $organization->seats_licensed);
        $this->assertSame(25, (int) $organization->session_bundle_sessions);
    }

    public function test_network_off_zeroes_sessions_and_keeps_seats(): void
    {
        [$organization] = $this->admin();

        $quote = $this->postJson("/api/v2/business/organization/seats", [
            "seats_licensed" => 250,
            "therapist_access" => false,
            "bundle_sessions" => 25,
        ])->assertStatus(200)->json("data.quote");

        $this->assertFalse($quote["uses_network"]);
        $this->assertSame(0, $quote["bundle_sessions"]);
        $this->assertSame(0, $quote["bundle_total"]);
        $this->assertFalse($quote["metered_sessions"]);
        $this->assertSame(1500000, $quote["total_monthly"]); // 250 × 6,000, seats only

        // The bundle is not silently retained while the network is off.
        $this->assertSame(0, (int) $organization->refresh()->session_bundle_sessions);
    }

    public function test_custom_bundle_prices_at_the_higher_rate(): void
    {
        [$organization] = $this->admin();

        $quote = $this->postJson("/api/v2/business/organization/seats", [
            "seats_licensed" => 250,
            "therapist_access" => true,
            "payment_timing" => "prepay",
            "bundle_sessions" => 18,
            "bundle_custom" => true,
        ])->assertStatus(200)->json("data.quote");

        // A custom (non-block) quantity prices at ₦8,240 (+3%), not ₦8,000.
        $this->assertTrue($quote["bundle_custom"]);
        $this->assertSame(8240, $quote["rates"]["session_applied"]);
        $this->assertSame(148320, $quote["bundle_total"]); // 18 × 8,240
        $this->assertSame(148320, $quote["due_now"]);

        $this->assertTrue((bool) $organization->refresh()->bundle_custom);
    }

    public function test_postpay_meters_sessions_and_buys_no_bundle(): void
    {
        [$organization] = $this->admin();

        $quote = $this->postJson("/api/v2/business/organization/seats", [
            "seats_licensed" => 250,
            "therapist_access" => true,
            "payment_timing" => "postpay",
            "bundle_sessions" => 25, // ignored under postpay
            "bundle_custom" => true,
        ])->assertStatus(200)->json("data.quote");

        $this->assertSame("postpay", $quote["payment_timing"]);
        $this->assertTrue($quote["metered_sessions"]);
        $this->assertSame(8240, $quote["rates"]["metered_session"]); // pay-as-you-go rate
        $this->assertSame(0, $quote["bundle_sessions"]);
        $this->assertSame(0, $quote["bundle_total"]);
        $this->assertSame(0, $quote["due_now"]);
        $this->assertSame(1500000, $quote["total_monthly"]); // seats only

        // Postpay stores no prepaid bundle.
        $organization->refresh();
        $this->assertSame(0, (int) $organization->session_bundle_sessions);
        $this->assertSame("postpay", $organization->payment_timing);
    }

    public function test_zero_or_negative_seats_rejected(): void
    {
        $this->admin();

        foreach ([0, -5] as $seats) {
            $this->postJson("/api/v2/business/organization/seats", [
                "seats_licensed" => $seats,
                "therapist_access" => true,
            ])->assertStatus(422);
        }
    }

    public function test_seats_cannot_be_cut_below_seats_already_in_use(): void
    {
        [$organization] = $this->admin();
        $organization->update(["seats_licensed" => 50]);

        // Three active members already hold seats.
        OrganizationMember::factory()->count(3)->create(["organization_id" => $organization->id]);

        $this->postJson("/api/v2/business/organization/seats", [
            "seats_licensed" => 2,
            "therapist_access" => true,
        ])->assertStatus(400);

        $this->assertSame(50, (int) $organization->refresh()->seats_licensed);
    }

    public function test_plan_saves_the_pay_method_and_returns_bank_details(): void
    {
        [$organization] = $this->admin();

        $this->postJson("/api/v2/business/organization/plan", ["pay_method" => "invoice"])
            ->assertStatus(200)
            ->assertJsonPath("data.bank_details.bank", "GTBank")
            ->assertJsonPath("data.plan.name", "Wellbeing Lite");

        $this->assertSame("invoice", $organization->refresh()->pay_method);
    }

    public function test_plan_rejects_an_unknown_pay_method(): void
    {
        [$organization] = $this->admin();
        $organization->update(["pay_method" => "card"]);

        $this->postJson("/api/v2/business/organization/plan", ["pay_method" => "crypto"])
            ->assertStatus(422);

        $this->assertSame("card", $organization->refresh()->pay_method);
    }

    public function test_bench_saves_topics_and_reports_the_verified_count(): void
    {
        [$organization] = $this->admin();

        Therapist::create(["status" => StatusConstants::ACTIVE, "verified_at" => now()]);
        Therapist::create(["status" => StatusConstants::ACTIVE, "verified_at" => now()]);
        Therapist::create(["status" => StatusConstants::ACTIVE]); // unverified

        $this->postJson("/api/v2/business/organization/bench", [
            "bench_topics" => ["anxiety", "work", "anxiety"],
        ])
            ->assertStatus(200)
            ->assertJsonPath("data.bench.verified_therapist_count", 2);

        $this->assertSame(["anxiety", "work"], $organization->refresh()->bench_topics);
    }

    public function test_organization_read_returns_the_saved_quote_and_available_bench_topics(): void
    {
        [$organization] = $this->admin();
        $organization->update(["seats_licensed" => 120, "therapist_access" => true, "session_bundle_sessions" => 10]);

        $this->getJson("/api/v2/business/organization")
            ->assertStatus(200)
            ->assertJsonPath("data.quote.seats", 120)
            ->assertJsonPath("data.quote.tier.price", 6000)
            ->assertJsonPath("data.quote.seats_monthly", 120 * 6000)  // tier rate, not flat
            ->assertJsonPath("data.quote.total_monthly", 120 * 6000)  // bundle is one-off
            ->assertJsonPath("data.quote.bundle_total", 10 * 8000)
            ->assertJsonCount(6, "data.bench.available");
    }
}
