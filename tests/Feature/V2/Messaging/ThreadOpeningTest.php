<?php

namespace Tests\Feature\V2\Messaging;

use App\Models\Conversation;
use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ThreadOpeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_pair_conversation_opens_active(): void
    {
        $session = TherapySession::factory()->create();
        $client = $session->user;
        $therapist_user = $session->therapist->user;

        Sanctum::actingAs($client);
        $this->postJson("/api/v2/user/messaging/conversations", [
            "receiver_id" => $therapist_user->id,
            "message" => "Hello, looking forward to our session",
        ])->assertStatus(200);

        $this->assertSame("Active", Conversation::first()->status);
    }

    public function test_first_message_flows_immediately_in_booking_pair_thread(): void
    {
        $session = TherapySession::factory()->create();

        Sanctum::actingAs($session->user);
        $this->postJson("/api/v2/user/messaging/conversations", [
            "receiver_id" => $session->therapist->user->id,
            "message" => "First message",
        ])->assertStatus(200);

        $this->assertDatabaseHas("messages", ["message" => "First message"]);
    }

    public function test_community_dm_still_awaiting_response(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        Sanctum::actingAs($sender);
        $this->postJson("/api/v2/user/messaging/conversations", [
            "receiver_id" => $receiver->id,
            "message" => "Hi there",
        ])->assertStatus(200);

        $this->assertSame("Awaiting_Response", Conversation::first()->status);
    }

    public function test_v1_request_model_unchanged(): void
    {
        // Even a booking pair opens AWAITING_RESPONSE on the v1 route.
        $session = TherapySession::factory()->create();

        Sanctum::actingAs($session->user);
        $this->postJson("/api/v1/user/messaging/conversations", [
            "receiver_id" => $session->therapist->user->id,
            "message" => "Via v1",
        ]);

        $this->assertSame("Awaiting_Response", Conversation::first()->status);
    }
}
