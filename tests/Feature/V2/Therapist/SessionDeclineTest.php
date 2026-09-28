<?php

namespace Tests\Feature\V2\Therapist;

use App\Models\Payment;
use App\Models\TherapistReview;
use App\Models\TherapySession;
use App\Services\Finance\PaymentGateways\Flutterwave\FlutterwaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Decline = the §08 therapist-initiated cancel (auto-refund) on the
 * therapist lane.
 */
class SessionDeclineTest extends TestCase
{
    use RefreshDatabase;

    private function paidSession(): TherapySession
    {
        $session = TherapySession::factory()->create(["starts_at" => now()->addHours(2)]);
        $payment = Payment::create([
            "user_id" => $session->user_id,
            "amount" => 15000,
            "reference" => "TK-SESS-" . fake()->unique()->numerify("######"),
            "activity" => "PAYMENT_FOR_SESSION",
            "type" => "Debit",
            "status" => "Completed",
        ]);
        $session->update(["payment_id" => $payment->id]);

        return $session;
    }

    public function test_decline_records_cancelled_by_therapist_and_dispatches_refund(): void
    {
        Notification::fake();
        $session = $this->paidSession();
        Sanctum::actingAs($session->therapist->user);
        $this->mock(FlutterwaveService::class, function ($mock) {
            $mock->shouldReceive("refundTransaction")->once()->andReturn(["status" => "success"]);
        });

        $this->postJson("/api/v2/user/bookings/{$session->id}/cancel", [
            "reason" => "Personal emergency",
        ])->assertStatus(200);

        $session->refresh();
        $this->assertSame("cancelled", $session->status);
        $this->assertSame("therapist", $session->cancelled_by);
    }

    public function test_decline_leaves_star_rating_untouched(): void
    {
        Notification::fake();
        $session = $this->paidSession();
        $therapist = $session->therapist;

        $reviewed = TherapySession::factory()->completed()->create(["therapist_id" => $therapist->id]);
        TherapistReview::factory()->create([
            "session_id" => $reviewed->id,
            "therapist_id" => $therapist->id,
            "rating" => 5,
        ]);

        Sanctum::actingAs($therapist->user);
        $this->mock(FlutterwaveService::class, function ($mock) {
            $mock->shouldReceive("refundTransaction")->andReturn(["status" => "success"]);
        });
        $this->postJson("/api/v2/user/bookings/{$session->id}/cancel", [
            "reason" => "Emergency",
        ])->assertStatus(200);

        // Rating aggregate untouched; the cancellation count derives from
        // cancelled_by instead.
        $this->assertEquals(5.0, (float) TherapistReview::where("therapist_id", $therapist->id)->avg("rating"));
        $this->assertSame(1, TherapySession::where("therapist_id", $therapist->id)
            ->where("cancelled_by", "therapist")->count());
    }
}
