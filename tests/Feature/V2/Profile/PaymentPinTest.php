<?php

namespace Tests\Feature\V2\Profile;

use App\Models\PaymentMethod;
use App\Models\TherapySession;
use App\Models\User;
use App\Services\Finance\PaymentGateways\Flutterwave\FlutterwaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentPinTest extends TestCase
{
    use RefreshDatabase;

    public function test_set_pin_stored_hashed_not_plaintext(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/user/security/payment-pin", ["pin" => "4321"])
            ->assertStatus(200);

        $user->refresh();
        $this->assertNotSame("4321", $user->payment_pin);
        $this->assertTrue(Hash::check("4321", $user->payment_pin));
    }

    public function test_invalid_pin_format_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        foreach (["12a4", "123", "12345"] as $bad) {
            $this->postJson("/api/v2/user/security/payment-pin", ["pin" => $bad])
                ->assertStatus(422);
        }
    }

    public function test_change_requires_current_pin_or_otp(): void
    {
        $user = User::factory()->create(["payment_pin" => Hash::make("1111")]);
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/user/security/payment-pin", [
            "pin" => "2222",
            "current_pin" => "9999",
        ])->assertStatus(400)->assertJson(["success" => false]);

        $this->postJson("/api/v2/user/security/payment-pin", [
            "pin" => "2222",
            "current_pin" => "1111",
        ])->assertStatus(200);

        $this->assertTrue(Hash::check("2222", $user->refresh()->payment_pin));
    }

    public function test_saved_card_charge_requires_valid_pin(): void
    {
        $user = User::factory()->create(["payment_pin" => Hash::make("1111")]);
        Sanctum::actingAs($user);
        $method = PaymentMethod::create([
            "user_id" => $user->id,
            "token" => "flw-tok-abc",
            "last4" => "4242",
        ]);
        $session = TherapySession::factory()->pending()->create(["user_id" => $user->id]);

        // Wrong PIN never reaches the provider.
        $this->mock(FlutterwaveService::class, function ($mock) {
            $mock->shouldReceive("chargeWithToken")->never();
        });
        $this->postJson("/api/v2/user/bookings/{$session->id}/initiate-payment", [
            "payment_method_id" => $method->id,
            "payment_pin" => "9999",
        ])->assertStatus(400)->assertJson(["success" => false]);

        // Correct PIN authorizes the tokenized charge.
        $this->mock(FlutterwaveService::class, function ($mock) {
            $mock->shouldReceive("chargeWithToken")->once()->andReturn(["status" => "successful"]);
        });
        $this->postJson("/api/v2/user/bookings/{$session->id}/initiate-payment", [
            "payment_method_id" => $method->id,
            "payment_pin" => "1111",
        ])->assertStatus(200)->assertJsonPath("data.charged", true);

        $this->assertSame("confirmed", $session->refresh()->status);
    }
}
