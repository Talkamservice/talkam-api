<?php

namespace Tests\Feature\V2\Sessions;

use App\Models\Payment;
use App\Models\SessionReschedule;
use App\Models\TherapySession;
use App\Models\User;
use App\Notifications\Therapist\SessionRescheduleNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RescheduleResponseTest extends TestCase
{
    use RefreshDatabase;

    private function pendingRequest(): SessionReschedule
    {
        $session = TherapySession::factory()->create();

        return SessionReschedule::factory()->create([
            "session_id" => $session->id,
            "requested_by" => $session->user_id,
        ]);
    }

    public function test_accept_moves_slot_same_price_without_new_payment(): void
    {
        Notification::fake();
        $reschedule = $this->pendingRequest();
        $session = $reschedule->session;
        $original_amount = $session->amount;
        $payments_before = Payment::count();

        Sanctum::actingAs($session->therapist->user);
        $this->postJson("/api/v2/user/reschedules/{$reschedule->id}/respond", ["action" => "accept"])
            ->assertStatus(200)->assertJsonPath("data.status", "accepted");

        $session->refresh();
        $this->assertEquals($reschedule->new_starts_at->toDateTimeString(), $session->starts_at->toDateTimeString());
        $this->assertEquals($original_amount, $session->amount);
        $this->assertSame($payments_before, Payment::count());
        Notification::assertSentTo($session->user, SessionRescheduleNotification::class);
    }

    public function test_decline_marks_request_and_notifies_requester(): void
    {
        Notification::fake();
        $reschedule = $this->pendingRequest();
        $session = $reschedule->session;
        $original_starts = $session->starts_at;

        Sanctum::actingAs($session->therapist->user);
        $this->postJson("/api/v2/user/reschedules/{$reschedule->id}/respond", ["action" => "decline"])
            ->assertStatus(200)->assertJsonPath("data.status", "declined");

        $this->assertEquals($original_starts, $session->refresh()->starts_at);
        Notification::assertSentTo($session->user, SessionRescheduleNotification::class);
    }

    public function test_requester_cannot_respond_to_own_request(): void
    {
        $reschedule = $this->pendingRequest();
        Sanctum::actingAs($reschedule->requester);

        $this->postJson("/api/v2/user/reschedules/{$reschedule->id}/respond", ["action" => "accept"])
            ->assertStatus(400)->assertJson(["success" => false]);
    }

    public function test_unrelated_user_rejected(): void
    {
        $reschedule = $this->pendingRequest();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v2/user/reschedules/{$reschedule->id}/respond", ["action" => "accept"])
            ->assertStatus(404);
    }

    public function test_non_pending_request_rejected(): void
    {
        Notification::fake();
        $reschedule = $this->pendingRequest();
        $reschedule->update(["status" => "declined"]);

        Sanctum::actingAs($reschedule->session->therapist->user);
        $this->postJson("/api/v2/user/reschedules/{$reschedule->id}/respond", ["action" => "accept"])
            ->assertStatus(400);
    }
}
