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

    /**
     * Real Agora Notifications shape: {noticeId, productId, eventType,
     * notifyMs, payload: {channelName, ...}}, signed over the raw body with
     * HMAC-SHA256 in the `Agora-Signature-V2` header.
     * https://docs.agora.io/en/video-calling/channel-management-api/webhook/channel-event-type
     */
    private function signedPost(int $event_type, ?string $channel_name, ?string $secret = "test-webhook-secret")
    {
        $payload = [
            "noticeId" => "test-notice-id",
            "productId" => 1,
            "eventType" => $event_type,
            "notifyMs" => now()->valueOf(),
            "payload" => array_filter(["channelName" => $channel_name]),
        ];

        $body = json_encode($payload);
        $signature = hash_hmac("sha256", $body, $secret);

        return $this->call(
            "POST",
            "/api/v2/webhooks/av-provider",
            [],
            [],
            [],
            [
                "HTTP_Agora-Signature-V2" => $signature,
                "CONTENT_TYPE" => "application/json",
                "HTTP_ACCEPT" => "application/json",
            ],
            $body
        );
    }

    public function test_channel_destroy_event_completes_session(): void
    {
        $session = TherapySession::factory()->create([
            "status" => "in_progress",
            "channel_ref" => "TKSESS-ABC123",
            "started_at" => now()->subMinutes(50),
        ]);

        $this->signedPost(102, "TKSESS-ABC123")->assertStatus(200);

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

        $this->signedPost(102, "TKSESS-ABC123", "wrong-secret")->assertStatus(401);

        $this->assertSame("in_progress", $session->refresh()->status);
    }

    public function test_unknown_channel_ref_is_noop(): void
    {
        $session = TherapySession::factory()->create([
            "status" => "in_progress",
            "channel_ref" => "TKSESS-ABC123",
        ]);

        $this->signedPost(102, "TKSESS-UNKNOWN")->assertStatus(200);

        $this->assertSame("in_progress", $session->refresh()->status);
    }

    public function test_unrelated_event_type_is_noop(): void
    {
        $session = TherapySession::factory()->create([
            "status" => "in_progress",
            "channel_ref" => "TKSESS-ABC123",
        ]);

        // 107 = user join (communication profile) — not a completion signal.
        $this->signedPost(107, "TKSESS-ABC123")->assertStatus(200);

        $this->assertSame("in_progress", $session->refresh()->status);
    }
}
