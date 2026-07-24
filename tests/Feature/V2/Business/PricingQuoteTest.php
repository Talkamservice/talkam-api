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

    public function test_pricing_config_is_public_and_carries_every_deck_constant(): void
    {
        $this->getJson("/api/v2/business/pricing-config")
            ->assertStatus(200)
            ->assertJsonPath("data.employee_seat_rate", 2000)
            ->assertJsonPath("data.therapist_access_rate", 3500)
            ->assertJsonPath("data.session_rate", 8000)
            ->assertJsonPath("data.standard_therapist_rate", 15000)
            ->assertJsonPath("data.network_average_rate", 15450)
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

    public function test_quote_totals_match_the_deck_arithmetic(): void
    {
        [$organization] = $this->admin();

        $response = $this->postJson("/api/v2/business/organization/seats", [
            "seats_licensed" => 250,
            "therapist_access" => true,
            "bundle_sessions" => 25,
        ])->assertStatus(200);

        $quote = $response->json("data.quote");

        $this->assertSame(500000, $quote["employee_seats_monthly"]);   // 250 x 2000
        $this->assertSame(875000, $quote["therapist_access_monthly"]); // 250 x 3500
        $this->assertSame(200000, $quote["session_bundle_monthly"]);   // 25 x 8000
        $this->assertSame(1575000, $quote["total_monthly"]);
        $this->assertSame(6000, $quote["tier"]["price"]);

        $organization->refresh();
        $this->assertSame(250, (int) $organization->seats_licensed);
        $this->assertSame(25, (int) $organization->session_bundle_sessions);
    }

    public function test_therapist_access_off_zeroes_the_therapist_and_bundle_lines(): void
    {
        [$organization] = $this->admin();

        $quote = $this->postJson("/api/v2/business/organization/seats", [
            "seats_licensed" => 250,
            "therapist_access" => false,
            "bundle_sessions" => 25,
        ])->assertStatus(200)->json("data.quote");

        $this->assertSame(0, $quote["therapist_access_monthly"]);
        $this->assertSame(0, $quote["session_bundle_monthly"]);
        $this->assertSame(500000, $quote["total_monthly"]);

        // The bundle is not silently retained while access is off.
        $this->assertSame(0, (int) $organization->refresh()->session_bundle_sessions);
    }

    public function test_blended_per_seat_uses_the_live_network_average(): void
    {
        [$organization] = $this->admin();

        // Two therapists at 16,000 and 20,000 → mean 18,000.
        Therapist::create(["status" => StatusConstants::ACTIVE, "session_rate" => 16000]);
        Therapist::create(["status" => StatusConstants::ACTIVE, "session_rate" => 20000]);

        $quote = $this->postJson("/api/v2/business/organization/seats", [
            "seats_licensed" => 250,
            "therapist_access" => true,
            "bundle_sessions" => 0,
        ])->assertStatus(200)->json("data.quote");

        $this->assertSame(18000.0, (float) $quote["blended"]["network_average_rate"]);
        $this->assertSame(1.2, (float) $quote["blended"]["fairness_multiplier"]); // 18000 / 15000
        $this->assertSame(7200.0, (float) $quote["blended"]["per_seat"]);         // 6000 x 1.2
    }

    public function test_blended_falls_back_to_config_when_the_bench_is_empty(): void
    {
        $this->admin();

        $quote = $this->postJson("/api/v2/business/organization/seats", [
            "seats_licensed" => 250,
            "therapist_access" => true,
            "bundle_sessions" => 0,
        ])->assertStatus(200)->json("data.quote");

        $this->assertSame(15450.0, (float) $quote["blended"]["network_average_rate"]);
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
            ->assertJsonPath("data.quote.total_monthly", 120 * 2000 + 120 * 3500 + 10 * 8000)
            ->assertJsonCount(6, "data.bench.available");
    }
}
