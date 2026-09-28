<?php

namespace Tests\Feature\V2\Sessions;

use App\Models\Payment;
use App\Models\TherapySession;
use App\Models\User;
use App\Services\Finance\PaymentGateways\Flutterwave\FlutterwaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionCompletionSweepTest extends TestCase
{
    use RefreshDatabase;

    private function paidEndedSession(array $overrides = []): TherapySession
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
            "starts_at" => now()->subHours(2),
        ], $overrides));
    }

    public function test_ended_sessions_with_join_marked_completed(): void
    {
        $this->mock(FlutterwaveService::class, function ($mock) {
            $mock->shouldReceive("refundTransaction")->never();
        });
        $session = $this->paidEndedSession([
            "status" => "in_progress",
            "started_at" => now()->subHours(2),
            "client_joined_at" => now()->subHours(2),
            "therapist_joined_at" => now()->subHours(2),
        ]);

        $this->artisan("bookings:sweep-session-completions")->assertSuccessful();

        $this->assertSame("completed", $session->refresh()->status);
    }

    public function test_client_no_show_marked_without_refund(): void
    {
        $this->mock(FlutterwaveService::class, function ($mock) {
            $mock->shouldReceive("refundTransaction")->never();
        });
        $session = $this->paidEndedSession(["status" => "confirmed"]);

        $this->artisan("bookings:sweep-session-completions")->assertSuccessful();

        $this->assertSame("no_show", $session->refresh()->status);
    }

    public function test_therapist_no_show_triggers_full_refund(): void
    {
        $this->mock(FlutterwaveService::class, function ($mock) {
            $mock->shouldReceive("refundTransaction")->once()->andReturn(["status" => "success"]);
        });
        $session = $this->paidEndedSession([
            "status" => "in_progress",
            "started_at" => now()->subHours(2),
            "client_joined_at" => now()->subHours(2),
            "therapist_joined_at" => null,
        ]);

        $this->artisan("bookings:sweep-session-completions")->assertSuccessful();

        $this->assertSame("no_show", $session->refresh()->status);
    }

    public function test_sessions_before_end_time_untouched(): void
    {
        $this->mock(FlutterwaveService::class, function ($mock) {
            $mock->shouldReceive("refundTransaction")->never();
        });
        $session = $this->paidEndedSession(["starts_at" => now()->addHour()]);

        $this->artisan("bookings:sweep-session-completions")->assertSuccessful();

        $this->assertSame("confirmed", $session->refresh()->status);
    }
}
