<?php

namespace Tests\Feature\V2\Sessions;

use App\Models\TherapySession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvProviderWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(["services.agora.webhook_secret" => "test-webhook-secret"]);
    }

    private function signedPost(array $payload, ?string $secret = "test-webhook-secret")
    {
        $body = json_encode($payload);
        $signature = hash_hmac("sha256", $body, $secret);

        return $this->call(
            "POST",
            "/api/v2/webhooks/av-provider",
            [],
            [],
            [],
            [
                "HTTP_X-AV-SIGNATURE" => $signature,
                "CONTENT_TYPE" => "application/json",
                "HTTP_ACCEPT" => "application/json",
            ],
            $body
        );
    }

    public function test_room_closed_event_completes_session(): void
    {
        $session = TherapySession::factory()->create([
            "status" => "in_progress",
            "channel_ref" => "TKSESS-ABC123",
            "started_at" => now()->subMinutes(50),
        ]);

        $this->signedPost([
            "event" => "room_closed",
            "channel_ref" => "TKSESS-ABC123",
        ])->assertStatus(200);

        $session->refresh();
        $this->assertSame("completed", $session->status);
        $this->assertNotNull($session->ended_at);
    }

    public function test_unverified_payload_rejected(): void
    {
        $session = TherapySession::factory()->create([
            "status" => "in_progress",
            "channel_ref" => "TKSESS-ABC123",
        ]);

        $this->signedPost([
            "event" => "room_closed",
            "channel_ref" => "TKSESS-ABC123",
        ], "wrong-secret")->assertStatus(401);

        $this->assertSame("in_progress", $session->refresh()->status);
    }

    public function test_unknown_channel_ref_is_noop(): void
    {
        $session = TherapySession::factory()->create([
            "status" => "in_progress",
            "channel_ref" => "TKSESS-ABC123",
        ]);

        $this->signedPost([
            "event" => "room_closed",
            "channel_ref" => "TKSESS-UNKNOWN",
        ])->assertStatus(200);

        $this->assertSame("in_progress", $session->refresh()->status);
    }
}
