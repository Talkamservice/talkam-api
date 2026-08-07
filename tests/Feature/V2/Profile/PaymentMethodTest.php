<?php

namespace Tests\Feature\V2\Profile;

use App\Models\PaymentMethod;
use App\Models\TherapySession;
use App\Models\User;
use App\Services\Finance\PaymentGateways\Flutterwave\FlutterwaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    private function checkoutWithCallback(User $user, bool $save_card): void
    {
        Notification::fake();
        Sanctum::actingAs($user);
        $session = TherapySession::factory()->pending()->create(["user_id" => $user->id]);

        $reference = $this->postJson(
            "/api/v2/user/bookings/{$session->id}/initiate-payment",
            ["save_card" => $save_card]
        )->json("data.reference");

        $this->mock(FlutterwaveService::class, function ($mock) use ($reference) {
            $mock->shouldReceive("verifyTransactionByReference")->andReturn([
                "data" => [
                    "status" => "successful",
                    "tx_ref" => $reference,
                    "meta" => ["activity" => "PAYMENT_FOR_SESSION"],
                    "card" => [
                        "token" => "flw-tok-abc123",
                        "last_4digits" => "4242",
                        "type" => "VISA",
                        "expiry" => "09/32",
                    ],
                ],
            ]);
        });

        $this->postJson("/api/v2/finance/payments/callback", ["reference" => $reference])
            ->assertStatus(200);
    }

    public function test_opt_in_tokenization_stores_card_from_charge_response(): void
    {
        $user = User::factory()->create();
        $this->checkoutWithCallback($user, save_card: true);

        $this->assertDatabaseHas("payment_methods", [
            "user_id" => $user->id,
            "last4" => "4242",
            "brand" => "VISA",
            "exp_year" => "2032",
        ]);
    }

    public function test_opt_out_stores_nothing(): void
    {
        $user = User::factory()->create();
        $this->checkoutWithCallback($user, save_card: false);

        $this->assertSame(0, PaymentMethod::count());
    }

    public function test_lists_only_own_cards_without_raw_token(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        PaymentMethod::create([
            "user_id" => $user->id,
            "token" => "flw-tok-own",
            "last4" => "4242",
            "brand" => "VISA",
        ]);
        PaymentMethod::create([
            "user_id" => User::factory()->create()->id,
            "token" => "flw-tok-other",
            "last4" => "1111",
        ]);

        $data = $this->getJson("/api/v2/user/payment-methods")->assertStatus(200)->json("data");

        $this->assertCount(1, $data);
        $this->assertSame("4242", $data[0]["last4"]);
        $this->assertArrayNotHasKey("token", $data[0]);
    }

    public function test_delete_own_card(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $method = PaymentMethod::create([
            "user_id" => $user->id,
            "token" => "flw-tok-own",
            "last4" => "4242",
        ]);

        $this->deleteJson("/api/v2/user/payment-methods/{$method->id}")->assertStatus(200);
        $this->assertSame(0, PaymentMethod::count());
    }

    public function test_cannot_delete_foreign_card(): void
    {
        $method = PaymentMethod::create([
            "user_id" => User::factory()->create()->id,
            "token" => "flw-tok-other",
            "last4" => "1111",
        ]);
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson("/api/v2/user/payment-methods/{$method->id}")->assertStatus(404);
        $this->assertSame(1, PaymentMethod::count());
    }
}
