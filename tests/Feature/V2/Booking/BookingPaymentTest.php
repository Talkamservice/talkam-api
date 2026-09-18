<?php

namespace Tests\Feature\V2\Booking;

use App\Constants\Finance\Payment\PaymentConstants;
use App\Models\Payment;
use App\Models\TherapySession;
use App\Models\User;
use App\Notifications\Therapist\SessionBookedNotification;
use App\Services\Finance\PaymentGateways\Flutterwave\FlutterwaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function pendingBookingWithPayment(User $user): array
    {
        Sanctum::actingAs($user);
        $session = TherapySession::factory()->pending()->create(["user_id" => $user->id]);
        $this->fakeCheckoutLink();
        $reference = $this->postJson("/api/v2/user/bookings/{$session->id}/initiate-payment")
            ->assertStatus(200)
            ->json("data.reference");

        return [$session, $reference];
    }

    /**
     * initiatePayment() now also asks Flutterwave for a hosted checkout
     * link — stub it so tests stay offline instead of hitting the real
     * sandbox API on every run.
     */
    private function fakeCheckoutLink(): void
    {
        $this->mock(FlutterwaveService::class, function ($mock) {
            $mock->shouldReceive("createCheckoutLink")->andReturn([
                "link" => "https://checkout.flutterwave.com/v3/hosted/pay/fake",
            ]);
        });
    }

    private function fakeVerification(string $reference, string $status = "successful"): void
    {
        $this->mock(FlutterwaveService::class, function ($mock) use ($reference, $status) {
            $mock->shouldReceive("verifyTransactionByReference")->andReturn([
                "data" => [
                    "status" => $status,
                    "tx_ref" => $reference,
                    "meta" => ["activity" => PaymentConstants::PAYMENT_FOR_SESSION],
                ],
            ]);
        });
    }

    public function test_initiate_returns_checkout_payload_and_payment_row(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $session = TherapySession::factory()->pending()->create(["user_id" => $user->id]);
        $this->fakeCheckoutLink();

        $response = $this->postJson("/api/v2/user/bookings/{$session->id}/initiate-payment")
            ->assertStatus(200)
            ->assertJsonPath("data.link", "https://checkout.flutterwave.com/v3/hosted/pay/fake");
        $reference = $response->json("data.reference");

        $this->assertDatabaseHas("payments", [
            "reference" => $reference,
            "activity" => PaymentConstants::PAYMENT_FOR_SESSION,
            "user_id" => $user->id,
        ]);
        $payment = Payment::where("reference", $reference)->first();
        $this->assertEquals($session->amount, $payment->amount);
        $this->assertEquals($session->id, $payment->metadata["booking_id"]);
    }

    /**
     * The hosted link is a nice-to-have for mobile — a Flutterwave outage
     * on this one call must never block booking a session at all (web
     * doesn't even use the link, so there's nothing to hold up for it).
     */
    public function test_initiate_still_succeeds_when_checkout_link_creation_fails(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $session = TherapySession::factory()->pending()->create(["user_id" => $user->id]);
        $this->mock(FlutterwaveService::class, function ($mock) {
            $mock->shouldReceive("createCheckoutLink")->andThrow(new \App\Exceptions\Payment\FlutterwaveException("down"));
        });

        $this->postJson("/api/v2/user/bookings/{$session->id}/initiate-payment")
            ->assertStatus(200)
            ->assertJsonPath("data.link", null);
    }

    public function test_initiate_rejected_for_foreign_booking(): void
    {
        $session = TherapySession::factory()->pending()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v2/user/bookings/{$session->id}/initiate-payment")
            ->assertStatus(404);

        $this->assertSame(0, Payment::count());
    }

    /**
     * Regression for a real sandbox timing issue: a callback right after
     * checkout can beat Flutterwave's own tx_ref search index, so the first
     * verify comes back with no matching transaction ("meta" missing) for a
     * payment that actually succeeded. The callback should retry instead of
     * failing the booking outright.
     */
    public function test_callback_retries_verification_when_transaction_not_yet_indexed(): void
    {
        config(["services.flutterwave.verifyRetryDelayMs" => 0]);
        Notification::fake();
        $user = User::factory()->create();
        [$session, $reference] = $this->pendingBookingWithPayment($user);

        $this->mock(FlutterwaveService::class, function ($mock) use ($reference) {
            $mock->shouldReceive("verifyTransactionByReference")
                ->andReturn(
                    ["data" => []],
                    ["data" => []],
                    ["data" => [
                        "status" => "successful",
                        "tx_ref" => $reference,
                        "meta" => ["activity" => PaymentConstants::PAYMENT_FOR_SESSION],
                    ]],
                );
        });

        $this->postJson("/api/v2/finance/payments/callback", ["reference" => $reference])
            ->assertStatus(200);

        $this->assertSame("confirmed", $session->refresh()->status);
    }

    public function test_callback_gives_up_after_retries_exhausted(): void
    {
        config(["services.flutterwave.verifyRetryDelayMs" => 0]);
        $user = User::factory()->create();
        [$session, $reference] = $this->pendingBookingWithPayment($user);

        $this->mock(FlutterwaveService::class, function ($mock) {
            $mock->shouldReceive("verifyTransactionByReference")->andReturn(["data" => []]);
        });

        $this->postJson("/api/v2/finance/payments/callback", ["reference" => $reference])
            ->assertStatus(400)
            ->assertJsonPath("message", "We could not ascertain the purpose of this payment");
    }

    public function test_callback_confirms_booking_and_links_payment(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        [$session, $reference] = $this->pendingBookingWithPayment($user);
        $this->fakeVerification($reference);

        $this->postJson("/api/v2/finance/payments/callback", ["reference" => $reference])
            ->assertStatus(200);

        $session->refresh();
        $this->assertSame("confirmed", $session->status);
        $this->assertNotNull($session->payment_id);
        Notification::assertSentTo($user, SessionBookedNotification::class);
    }

    public function test_callback_failure_marks_booking_failed(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        [$session, $reference] = $this->pendingBookingWithPayment($user);
        $this->fakeVerification($reference, "failed");

        $this->postJson("/api/v2/finance/payments/callback", ["reference" => $reference])
            ->assertStatus(400);

        $this->assertSame("failed", $session->refresh()->status);
    }

    public function test_retry_reinitiates_failed_booking(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $session = TherapySession::factory()->create([
            "user_id" => $user->id,
            "status" => "failed",
        ]);
        $this->fakeCheckoutLink();

        $this->postJson("/api/v2/user/bookings/{$session->id}/initiate-payment")
            ->assertStatus(200);

        $this->assertSame("pending_payment", $session->refresh()->status);
        $this->assertSame(1, Payment::count());
    }

    public function test_retry_rejected_for_confirmed_booking(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $session = TherapySession::factory()->create(["user_id" => $user->id]);

        $this->postJson("/api/v2/user/bookings/{$session->id}/initiate-payment")
            ->assertStatus(400);

        $this->assertSame(0, Payment::count());
    }

    /**
     * Flutterwave calls this server-to-server with no Sanctum token — it must
     * stay reachable as a guest. (Regression: the route was previously nested
     * inside the auth:sanctum group, so real webhook calls were 302-redirected
     * to /login instead of reaching the controller; every other test in this
     * file passes Sanctum::actingAs() first, which masked that.)
     */
    public function test_callback_is_reachable_without_authentication(): void
    {
        $this->postJson("/api/v2/finance/payments/callback", [])
            ->assertStatus(422);
    }

    public function test_v1_payment_activities_unaffected(): void
    {
        $user = User::factory()->create();
        [$session] = $this->pendingBookingWithPayment($user);

        // A promotion-activity payload (own reference) must never reach the
        // session handler — the pending booking stays untouched.
        $this->mock(FlutterwaveService::class, function ($mock) {
            $mock->shouldReceive("verifyTransactionByReference")->andReturn([
                "data" => [
                    "status" => "successful",
                    "tx_ref" => "PROMO-REF-123",
                    "meta" => ["activity" => PaymentConstants::PAYMENT_FOR_PROMOTION],
                ],
            ]);
        });

        $this->postJson("/api/v2/finance/payments/callback", ["reference" => "PROMO-REF-123"]);

        $this->assertSame("pending_payment", $session->refresh()->status);
    }
}
