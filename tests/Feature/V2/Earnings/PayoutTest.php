<?php

namespace Tests\Feature\V2\Earnings;

use App\Models\Payout;
use App\Models\Therapist;
use App\Models\TherapistPayoutAccount;
use App\Models\TherapistWalletTransaction;
use App\Models\User;
use App\Services\Finance\PaymentGateways\Flutterwave\FlutterwaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class PayoutTest extends TestCase
{
    use RefreshDatabase;

    private function therapistWithBalance(float $amount = 84000): Therapist
    {
        $therapist = Therapist::factory()->create();
        TherapistPayoutAccount::create([
            "user_id" => $therapist->user_id,
            "bank_code" => "058",
            "bank_name" => "GTBank",
            "account_number" => "0123455678",
            "account_name" => "ADA OBI",
            "verified_at" => now(),
        ]);
        if ($amount > 0) {
            TherapistWalletTransaction::create([
                "therapist_id" => $therapist->id,
                "type" => "credit",
                "amount" => $amount,
            ]);
        }

        return $therapist;
    }

    public function test_withdrawal_creates_pending_payout_and_full_balance_debit(): void
    {
        $therapist = $this->therapistWithBalance(84000);
        Sanctum::actingAs($therapist->user);
        $this->mock(FlutterwaveService::class, function ($mock) {
            $mock->shouldReceive("initiateTransfer")->once()->andReturn(["status" => "NEW"]);
        });

        $this->postJson("/api/v2/therapist/payouts")
            ->assertStatus(200)
            ->assertJsonPath("data.status", "pending");

        $this->assertDatabaseHas("payouts", ["therapist_id" => $therapist->id, "amount" => 84000]);
        $this->assertDatabaseHas("therapist_wallet_transactions", ["type" => "debit", "amount" => 84000]);
        $this->assertEquals(0, \App\Services\Therapist\EarningsLedgerService::balance($therapist));
    }

    public function test_partial_amount_input_is_not_honoured(): void
    {
        $therapist = $this->therapistWithBalance(84000);
        Sanctum::actingAs($therapist->user);
        $this->mock(FlutterwaveService::class, function ($mock) {
            $mock->shouldReceive("initiateTransfer")->andReturn(["status" => "NEW"]);
        });

        $this->postJson("/api/v2/therapist/payouts", ["amount" => 100])->assertStatus(200);

        $this->assertEquals(84000, (float) Payout::first()->amount);
    }

    public function test_below_minimum_balance_is_rejected(): void
    {
        $therapist = $this->therapistWithBalance(config("therapist.payout.minimum") - 1);
        Sanctum::actingAs($therapist->user);
        $this->mock(FlutterwaveService::class, function ($mock) {
            $mock->shouldReceive("initiateTransfer")->never();
        });

        $this->postJson("/api/v2/therapist/payouts")->assertStatus(400);

        $this->assertSame(0, Payout::count());
        $this->assertSame(0, TherapistWalletTransaction::where("type", "debit")->count());
    }

    public function test_zero_balance_is_rejected(): void
    {
        $therapist = $this->therapistWithBalance(0);
        Sanctum::actingAs($therapist->user);

        $this->postJson("/api/v2/therapist/payouts")->assertStatus(400);

        $this->assertSame(0, Payout::count());
    }

    public function test_transfer_request_contains_no_bvn(): void
    {
        $therapist = $this->therapistWithBalance(50000);
        Sanctum::actingAs($therapist->user);
        $this->mock(FlutterwaveService::class, function ($mock) {
            $mock->shouldReceive("initiateTransfer")
                ->once()
                ->with(Mockery::on(fn ($data) => !array_key_exists("bvn", $data)))
                ->andReturn(["status" => "NEW"]);
        });

        $this->postJson("/api/v2/therapist/payouts")->assertStatus(200);
    }

    public function test_show_returns_payout_status(): void
    {
        $therapist = $this->therapistWithBalance(50000);
        Sanctum::actingAs($therapist->user);
        $this->mock(FlutterwaveService::class, function ($mock) {
            $mock->shouldReceive("initiateTransfer")->andReturn(["status" => "NEW"]);
        });
        $id = $this->postJson("/api/v2/therapist/payouts")->json("data.id");

        $this->getJson("/api/v2/therapist/payouts/{$id}")
            ->assertStatus(200)
            ->assertJsonPath("data.status", "pending");
    }

    public function test_therapist_cannot_view_another_therapists_payout(): void
    {
        $therapist = $this->therapistWithBalance(50000);
        Sanctum::actingAs($therapist->user);
        $this->mock(FlutterwaveService::class, function ($mock) {
            $mock->shouldReceive("initiateTransfer")->andReturn(["status" => "NEW"]);
        });
        $id = $this->postJson("/api/v2/therapist/payouts")->json("data.id");

        Sanctum::actingAs(Therapist::factory()->create()->user);
        $this->getJson("/api/v2/therapist/payouts/{$id}")->assertStatus(404);
    }

    public function test_guest_gets_401(): void
    {
        $this->postJson("/api/v2/therapist/payouts")->assertStatus(401);
    }

    public function test_plain_user_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/v2/therapist/payouts")->assertStatus(403);
    }
}
