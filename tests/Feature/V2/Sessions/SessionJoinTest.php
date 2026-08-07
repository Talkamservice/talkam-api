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
        config([
            "services.agora.app_id" => "test-app-id",
            "services.agora.certificate" => "test-certificate",
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
            ->assertStatus(400)->assertJson(["success" => false]);
    }

    public function test_join_wrong_status_rejected(): void
    {
        foreach (["pending_payment", "cancelled", "completed"] as $status) {
            $session = TherapySession::factory()->create([
                "starts_at" => now()->subMinute(),
                "status" => $status,
            ]);
            Sanctum::actingAs($session->user);

            $this->getJson("/api/v2/user/bookings/{$session->id}/join")->assertStatus(400);
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
