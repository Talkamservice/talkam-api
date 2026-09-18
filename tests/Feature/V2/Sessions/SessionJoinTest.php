<?php

namespace Tests\Feature\V2\Sessions;

use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SessionJoinTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Agora's real token builder requires a 32-character hex app id/cert
        // (its isUUid() format check) — arbitrary strings silently produce
        // an empty token rather than throwing.
        config([
            "services.agora.app_id" => str_repeat("a", 32),
            "services.agora.certificate" => str_repeat("b", 32),
        ]);
    }

    public function test_join_within_window_returns_token_and_channel_ref(): void
    {
        $session = TherapySession::factory()->create(["starts_at" => now()->subMinute()]);
        Sanctum::actingAs($session->user);

        $response = $this->getJson("/api/v2/user/bookings/{$session->id}/join")
            ->assertStatus(200);

        $this->assertNotEmpty($response->json("data.token"));
        $this->assertNotEmpty($response->json("data.channel_ref"));
    }

    public function test_join_before_window_rejected(): void
    {
        $session = TherapySession::factory()->create(["starts_at" => now()->addMinutes(30)]);
        Sanctum::actingAs($session->user);

        $this->getJson("/api/v2/user/bookings/{$session->id}/join")
            ->assertStatus(400)
            ->assertJson(["success" => false])
            ->assertJsonPath(
                "message",
                "This session starts at {$session->starts_at->format('g:ia')} — "
                . "you can join from {$session->starts_at->format('g:ia')}."
            );
    }

    public function test_join_before_window_names_the_day_when_not_today(): void
    {
        $tomorrow = TherapySession::factory()->create(["starts_at" => now()->addDay()->setTime(8, 0)]);
        Sanctum::actingAs($tomorrow->user);

        $this->getJson("/api/v2/user/bookings/{$tomorrow->id}/join")
            ->assertStatus(400)
            ->assertJsonPath(
                "message",
                "This session starts tomorrow at 8:00am — you can join from 8:00am."
            );

        $in_three_days = TherapySession::factory()->create(["starts_at" => now()->addDays(3)->setTime(8, 0)]);
        Sanctum::actingAs($in_three_days->user);

        $this->getJson("/api/v2/user/bookings/{$in_three_days->id}/join")
            ->assertStatus(400)
            ->assertJsonPath(
                "message",
                "This session starts in 3 days at 8:00am — you can join from 8:00am."
            );
    }

    public function test_join_wrong_status_rejected(): void
    {
        $expected_messages = [
            "pending_payment" => "Payment for this session hasn't been completed yet.",
            "cancelled" => "This session was cancelled.",
            "completed" => "This session has already ended.",
        ];

        foreach ($expected_messages as $status => $message) {
            $session = TherapySession::factory()->create([
                "starts_at" => now()->subMinute(),
                "status" => $status,
            ]);
            Sanctum::actingAs($session->user);

            $this->getJson("/api/v2/user/bookings/{$session->id}/join")
                ->assertStatus(400)
                ->assertJsonPath("message", $message);
        }
    }

    public function test_first_join_stamps_started_at_and_in_progress(): void
    {
        $session = TherapySession::factory()->create(["starts_at" => now()->subMinute()]);
        Sanctum::actingAs($session->user);

        $this->getJson("/api/v2/user/bookings/{$session->id}/join")->assertStatus(200);

        $session->refresh();
        $this->assertSame("in_progress", $session->status);
        $this->assertNotNull($session->started_at);
        $this->assertNotNull($session->client_joined_at);
    }

    public function test_second_join_does_not_restamp_started_at(): void
    {
        $session = TherapySession::factory()->create(["starts_at" => now()->subMinute()]);
        Sanctum::actingAs($session->user);

        $this->getJson("/api/v2/user/bookings/{$session->id}/join")->assertStatus(200);
        $first_stamp = $session->refresh()->started_at;

        $this->travel(2)->minutes();
        $this->getJson("/api/v2/user/bookings/{$session->id}/join")->assertStatus(200);

        $this->assertEquals($first_stamp, $session->refresh()->started_at);
    }

    public function test_therapist_join_stamps_therapist_joined_at(): void
    {
        $session = TherapySession::factory()->create(["starts_at" => now()->subMinute()]);
        Sanctum::actingAs($session->therapist->user);

        $this->getJson("/api/v2/user/bookings/{$session->id}/join")->assertStatus(200);

        $this->assertNotNull($session->refresh()->therapist_joined_at);
        $this->assertNull($session->client_joined_at);
    }

    public function test_non_participant_rejected(): void
    {
        $session = TherapySession::factory()->create(["starts_at" => now()->subMinute()]);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v2/user/bookings/{$session->id}/join")->assertStatus(404);
    }
}
