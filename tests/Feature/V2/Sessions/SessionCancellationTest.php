<?php

namespace Tests\Feature\V2\Sessions;

use App\Models\Payment;
use App\Models\Therapist;
use App\Models\TherapistAvailability;
use App\Models\TherapySession;
use App\Models\User;
use App\Services\Finance\PaymentGateways\Flutterwave\FlutterwaveService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SessionCancellationTest extends TestCase
{
    use RefreshDatabase;

    private function paidSession(array $overrides = []): TherapySession
    {
        $user = User::factory()->create();
        $payment = Payment::create([
            "user_id" => $user->id,
            "amount" => 15000,
            "reference" => "TK-SESS-" . fake()->unique()->numerify("######"),
            "activity" => "PAYMENT_FOR_SESSION",
            "type" => "Debit",
            "status" => "Completed",
        ]);

        return TherapySession::factory()->create(array_merge([
            "user_id" => $user->id,
            "payment_id" => $payment->id,
            "starts_at" => now()->addHours(48),
        ], $overrides));
    }

    public function test_cancel_over_24h_before_start_initiates_full_refund(): void
    {
        Notification::fake();
        $session = $this->paidSession();
        Sanctum::actingAs($session->user);
        $this->mock(FlutterwaveService::class, function ($mock) {
            $mock->shouldReceive("refundTransaction")->once()->andReturn(["status" => "success"]);
        });

        $this->postJson("/api/v2/user/bookings/{$session->id}/cancel", [
            "reason" => "Personal emergency",
        ])->assertStatus(200);

        $this->assertSame("cancelled", $session->refresh()->status);
    }

    public function test_cancel_inside_24h_gets_no_refund(): void
    {
        Notification::fake();
        $session = $this->paidSession(["starts_at" => now()->addHours(3)]);
        Sanctum::actingAs($session->user);
        $this->mock(FlutterwaveService::class, function ($mock) {
            $mock->shouldReceive("refundTransaction")->never();
        });

        $this->postJson("/api/v2/user/bookings/{$session->id}/cancel")->assertStatus(200);

        $this->assertSame("cancelled", $session->refresh()->status);
        $this->assertSame("client", $session->cancelled_by);
    }

    public function test_therapist_cancel_always_refunds(): void
    {
        Notification::fake();
        $session = $this->paidSession(["starts_at" => now()->addHours(3)]);
        Sanctum::actingAs($session->therapist->user);
        $this->mock(FlutterwaveService::class, function ($mock) {
            $mock->shouldReceive("refundTransaction")->once()->andReturn(["status" => "success"]);
        });

        $this->postJson("/api/v2/user/bookings/{$session->id}/cancel")->assertStatus(200);

        $this->assertSame("therapist", $session->refresh()->cancelled_by);
    }

    public function test_cancel_records_actor_reason_and_frees_slot(): void
    {
        Notification::fake();
        $therapist = Therapist::factory()->create();
        TherapistAvailability::factory()->create([
            "user_id" => $therapist->user_id,
            "day_of_week" => "monday",
            "start_time" => "09:00",
            "end_time" => "12:00",
        ]);
        $monday = Carbon::parse("next monday");
        $session = $this->paidSession([
            "therapist_id" => $therapist->id,
            "starts_at" => $monday->copy()->setTime(10, 0),
        ]);
        Sanctum::actingAs($session->user);
        $this->mock(FlutterwaveService::class, function ($mock) {
            $mock->shouldReceive("refundTransaction")->andReturn(["status" => "success"]);
        });

        $this->postJson("/api/v2/user/bookings/{$session->id}/cancel", [
            "reason" => "Schedule conflict",
        ])->assertStatus(200);

        $this->assertSame("Schedule conflict", $session->refresh()->cancellation_reason);

        $date = $monday->toDateString();
        $starts = collect(
            $this->getJson("/api/v2/user/therapists/{$therapist->id}/slots?date=$date")->json("data.slots")
        )->pluck("starts_at");
        $this->assertTrue($starts->contains("$date 10:00:00"));
    }

    public function test_cannot_cancel_foreign_booking(): void
    {
        $session = $this->paidSession();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v2/user/bookings/{$session->id}/cancel")->assertStatus(404);

        $this->assertSame("confirmed", $session->refresh()->status);
    }

    public function test_terminal_statuses_cannot_be_cancelled(): void
    {
        foreach (["completed", "cancelled", "in_progress"] as $status) {
            $session = $this->paidSession(["status" => $status]);
            Sanctum::actingAs($session->user);

            $this->postJson("/api/v2/user/bookings/{$session->id}/cancel")
                ->assertStatus(400)->assertJson(["success" => false]);
        }
    }
}
