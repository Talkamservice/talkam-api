<?php

namespace Tests\Feature\V2\Therapist;

use App\Models\TherapistApplication;
use App\Models\TherapistPayoutAccount;
use App\Models\User;
use App\Services\Finance\PaymentGateways\Flutterwave\FlutterwaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PayoutStepTest extends TestCase
{
    use RefreshDatabase;

    private function mockResolution(): void
    {
        $this->mock(FlutterwaveService::class, function ($mock) {
            $mock->shouldReceive("resolveAccountNumber")
                ->andReturn(["account_name" => "ADA OBI"]);
        });
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            "bank_code" => "058",
            "bank_name" => "GTBank",
            "account_number" => "0123456789",
            "session_rate" => config("therapist.session_rate.min"),
        ], $overrides);
    }

    public function test_saves_verified_account_and_session_rate(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->mockResolution();

        $this->postJson("/api/v2/therapist/application/payout", $this->payload())
            ->assertStatus(200)->assertJson(["success" => true]);

        $this->assertDatabaseHas("therapist_payout_accounts", [
            "user_id" => $user->id,
            "bank_code" => "058",
            "account_name" => "ADA OBI",
            "provider" => "flutterwave",
        ]);
        $this->assertEquals(
            config("therapist.session_rate.min"),
            TherapistApplication::where("user_id", $user->id)->first()->session_rate
        );
    }

    public function test_rejects_rate_below_config_min(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->mockResolution();

        $this->postJson("/api/v2/therapist/application/payout", $this->payload([
            "session_rate" => config("therapist.session_rate.min") - 1,
        ]))->assertStatus(422)->assertJson(["success" => false]);

        $this->assertSame(0, TherapistPayoutAccount::count());
    }

    public function test_rejects_rate_above_config_max(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->mockResolution();

        $this->postJson("/api/v2/therapist/application/payout", $this->payload([
            "session_rate" => config("therapist.session_rate.max") + 1,
        ]))->assertStatus(422);

        $this->assertSame(0, TherapistPayoutAccount::count());
    }

    public function test_config_change_moves_rate_bounds(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->mockResolution();
        config(["therapist.session_rate.min" => 50000, "therapist.session_rate.max" => 90000]);

        // Old minimum now rejected; a value in the new range accepted.
        $this->postJson("/api/v2/therapist/application/payout", $this->payload([
            "session_rate" => 15000,
        ]))->assertStatus(422);

        $this->postJson("/api/v2/therapist/application/payout", $this->payload([
            "session_rate" => 60000,
        ]))->assertStatus(200);
    }

    public function test_bvn_field_never_persisted(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->mockResolution();

        $this->postJson("/api/v2/therapist/application/payout", $this->payload([
            "bvn" => "12345678901",
        ]))->assertStatus(200);

        // No bvn column exists on any therapist table (decision).
        $this->assertFalse(Schema::hasColumn("therapist_payout_accounts", "bvn"));
        $this->assertFalse(Schema::hasColumn("therapist_applications", "bvn"));
        $this->assertFalse(Schema::hasColumn("therapists", "bvn"));
    }

    public function test_requires_authentication(): void
    {
        $this->postJson("/api/v2/therapist/application/payout", [])->assertStatus(401);
    }
}
