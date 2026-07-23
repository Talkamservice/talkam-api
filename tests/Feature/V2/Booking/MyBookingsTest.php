<?php

namespace Tests\Feature\V2\Booking;

use App\Models\Payment;
use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MyBookingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_partitions_upcoming_and_past(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $upcoming = TherapySession::factory()->create([
            "user_id" => $user->id,
            "starts_at" => now()->addDays(3),
        ]);
        $past = TherapySession::factory()->completed()->create(["user_id" => $user->id]);
        TherapySession::factory()->create(); // someone else's

        $data = $this->getJson("/api/v2/user/bookings")->assertStatus(200)->json("data");

        $this->assertEquals([$upcoming->id], collect($data["upcoming"])->pluck("id")->all());
        $this->assertEquals([$past->id], collect($data["past"])->pluck("id")->all());
    }

    public function test_detail_includes_payment_reference_and_amount(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $payment = Payment::create([
            "user_id" => $user->id,
            "amount" => 15000,
            "reference" => "FLW-TK-29471",
            "activity" => "PAYMENT_FOR_SESSION",
            "type" => "Debit",
            "status" => "Completed",
        ]);
        $session = TherapySession::factory()->create([
            "user_id" => $user->id,
            "payment_id" => $payment->id,
        ]);

        $this->getJson("/api/v2/user/bookings/{$session->id}")
            ->assertStatus(200)
            ->assertJsonPath("data.payment_reference", "FLW-TK-29471")
            ->assertJsonPath("data.format", "video")
            ->assertJsonPath("data.duration_minutes", 50);
    }

    public function test_cannot_view_foreign_booking(): void
    {
        $session = TherapySession::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v2/user/bookings/{$session->id}")->assertStatus(404);
    }
}
