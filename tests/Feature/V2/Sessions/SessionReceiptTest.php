<?php

namespace Tests\Feature\V2\Sessions;

use App\Models\Payment;
use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SessionReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_serializes_payment_and_session(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $payment = Payment::create([
            "user_id" => $user->id,
            "amount" => 15000,
            "fees" => 100,
            "reference" => "FLW-TK-29471",
            "activity" => "PAYMENT_FOR_SESSION",
            "type" => "Debit",
            "narration" => "Therapy session",
            "status" => "Completed",
        ]);
        $session = TherapySession::factory()->create([
            "user_id" => $user->id,
            "payment_id" => $payment->id,
        ]);

        $this->getJson("/api/v2/user/bookings/{$session->id}/receipt")
            ->assertStatus(200)
            ->assertJsonPath("data.reference", "FLW-TK-29471")
            ->assertJsonPath("data.session.format", "video")
            ->assertJsonPath("data.session.duration_minutes", 50);
    }

    public function test_unpaid_booking_rejected(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $session = TherapySession::factory()->pending()->create(["user_id" => $user->id]);

        $this->getJson("/api/v2/user/bookings/{$session->id}/receipt")
            ->assertStatus(400)->assertJson(["success" => false]);
    }

    public function test_foreign_booking_rejected(): void
    {
        $session = TherapySession::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v2/user/bookings/{$session->id}/receipt")->assertStatus(404);
    }
}
