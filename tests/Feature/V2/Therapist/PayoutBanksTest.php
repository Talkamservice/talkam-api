<?php

namespace Tests\Feature\V2\Therapist;

use App\Exceptions\Payment\FlutterwaveException;
use App\Models\TherapistPayoutAccount;
use App\Models\User;
use App\Services\Finance\PaymentGateways\Flutterwave\FlutterwaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PayoutBanksTest extends TestCase
{
    use RefreshDatabase;

    public function test_banks_returns_mocked_flutterwave_list(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->mock(FlutterwaveService::class, function ($mock) {
            $mock->shouldReceive("getBanks")->once()->andReturn([
                ["code" => "058", "name" => "GTBank"],
                ["code" => "044", "name" => "Access Bank"],
            ]);
        });

        $response = $this->getJson("/api/v2/therapist/banks")
            ->assertStatus(200)->assertJson(["success" => true]);

        $names = collect($response->json("data"))->pluck("name");
        $this->assertTrue($names->contains("GTBank"));
    }

    public function test_verify_resolves_account_name_without_persisting(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->mock(FlutterwaveService::class, function ($mock) {
            $mock->shouldReceive("resolveAccountNumber")
                ->with("058", "0123456789")
                ->andReturn(["account_number" => "0123456789", "account_name" => "ADA OBI"]);
        });

        $this->postJson("/api/v2/therapist/payout-account/verify", [
            "bank_code" => "058",
            "account_number" => "0123456789",
        ])->assertStatus(200)->assertJsonPath("data.account_name", "ADA OBI");

        $this->assertSame(0, TherapistPayoutAccount::count());
    }

    public function test_verify_failure_persists_nothing(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->mock(FlutterwaveService::class, function ($mock) {
            $mock->shouldReceive("resolveAccountNumber")
                ->andThrow(new FlutterwaveException("Account resolution failed"));
        });

        $this->postJson("/api/v2/therapist/payout-account/verify", [
            "bank_code" => "058",
            "account_number" => "0000000000",
        ])->assertStatus(400)->assertJson(["success" => false]);

        $this->assertSame(0, TherapistPayoutAccount::count());
    }
}
