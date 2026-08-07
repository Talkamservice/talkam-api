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
        $reference = $this->postJson("/api/v2/user/bookings/{$session->id}/initiate-payment")
            ->assertStatus(200)
            ->json("data.reference");

        return [$session, $reference];
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
        [$session, $reference] = $this->pendingBookingWithPayment($user);

        $this->assertDatabaseHas("payments", [
            "reference" => $reference,
            "activity" => PaymentConstants::PAYMENT_FOR_SESSION,
            "user_id" => $user->id,
        ]);
        $payment = Payment::where("reference", $reference)->first();
        $this->assertEquals($session->amount, $payment->amount);
        $this->assertEquals($session->id, $payment->metadata["booking_id"]);
    }

    public function test_initiate_rejected_for_foreign_booking(): void
    {
        $session = TherapySession::factory()->pending()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v2/user/bookings/{$session->id}/initiate-payment")
            ->assertStatus(404);

        $this->assertSame(0, Payment::count());
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
