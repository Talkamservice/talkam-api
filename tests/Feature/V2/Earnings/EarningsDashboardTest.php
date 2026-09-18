<?php

namespace Tests\Feature\V2\Earnings;

use App\Models\Therapist;
use App\Models\TherapistWalletTransaction;
use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EarningsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_returns_envelope_with_documented_fields(): void
    {
        $therapist = Therapist::factory()->create();
        Sanctum::actingAs($therapist->user);

        $this->getJson("/api/v2/therapist/earnings/dashboard")
            ->assertStatus(200)
            ->assertJson(["success" => true, "code" => 200])
            ->assertJsonStructure(["data" => [
                "balance", "currency",
                "totals" => ["this_week", "this_month", "all_time"],
                "chart",
                "tiles" => ["sessions_this_week", "avg_per_session", "pending_payout"],
            ]]);
    }

    public function test_balance_is_credits_minus_debits(): void
    {
        $therapist = Therapist::factory()->create();
        TherapistWalletTransaction::create([
            "therapist_id" => $therapist->id, "type" => "credit", "amount" => 50000,
        ]);
        TherapistWalletTransaction::create([
            "therapist_id" => $therapist->id, "type" => "credit", "amount" => 34000,
        ]);
        TherapistWalletTransaction::create([
            "therapist_id" => $therapist->id, "type" => "debit", "amount" => 20000,
        ]);

        Sanctum::actingAs($therapist->user);
        $this->getJson("/api/v2/therapist/earnings/dashboard")
            ->assertJsonPath("data.balance", 64000);

        // No stored balance column anywhere in the flow.
        $this->assertFalse(Schema::hasColumn("therapists", "balance"));
        $this->assertFalse(Schema::hasColumn("therapist_wallet_transactions", "balance"));
    }

    public function test_pending_payout_derived_from_confirmed_sessions(): void
    {
        $therapist = Therapist::factory()->create();
        TherapySession::factory()->count(2)->create([
            "therapist_id" => $therapist->id,
            "amount" => 20000,
        ]);

        Sanctum::actingAs($therapist->user);
        $share = config("therapist.platform_share_percent");
        $expected = round(40000 * (1 - $share / 100), 2);

        $tile = $this->getJson("/api/v2/therapist/earnings/dashboard")->json("data.tiles.pending_payout");

        $this->assertSame(2, $tile["sessions"]);
        $this->assertEquals($expected, $tile["amount"]);
        // Pending sessions never create ledger rows.
        $this->assertSame(0, TherapistWalletTransaction::count());
    }

    public function test_response_contains_no_bvn_key(): void
    {
        $therapist = Therapist::factory()->create();
        Sanctum::actingAs($therapist->user);

        $payload = $this->getJson("/api/v2/therapist/earnings/dashboard")->content();

        $this->assertStringNotContainsStringIgnoringCase("bvn", $payload);
    }

    public function test_guest_gets_401(): void
    {
        $this->getJson("/api/v2/therapist/earnings/dashboard")->assertStatus(401);
    }

    public function test_plain_user_rejected_by_role_gate(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v2/therapist/earnings/dashboard")->assertStatus(403);
    }
}
