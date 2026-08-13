<?php

namespace Tests\Feature\V2\Sessions;

use App\Constants\General\StatusConstants;
use App\Models\Conversation;
use App\Models\TherapySession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SessionMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_starting_a_conversation_reaches_the_therapist(): void
    {
        $session = TherapySession::factory()->create();
        Sanctum::actingAs($session->user);

        $response = $this->postJson("/api/v2/user/bookings/{$session->id}/message")
            ->assertStatus(200);

        $response->assertJsonPath("data.other_member.id", $session->therapist->user_id);
        $response->assertJsonPath("data.status", StatusConstants::ACTIVE);

        $this->assertDatabaseCount("conversations", 1);
    }

    public function test_therapist_starting_a_conversation_reaches_the_client(): void
    {
        $session = TherapySession::factory()->create();
        Sanctum::actingAs($session->therapist->user);

        $response = $this->postJson("/api/v2/user/bookings/{$session->id}/message")
            ->assertStatus(200);

        $response->assertJsonPath("data.other_member.id", $session->user_id);
    }

    public function test_repeat_calls_reuse_the_same_conversation(): void
    {
        $session = TherapySession::factory()->create();
        Sanctum::actingAs($session->user);

        $first = $this->postJson("/api/v2/user/bookings/{$session->id}/message")->json("data.id");
        $second = $this->postJson("/api/v2/user/bookings/{$session->id}/message")->json("data.id");

        $this->assertSame($first, $second);
        $this->assertDatabaseCount("conversations", 1);
    }

    public function test_uninvolved_user_cannot_start_a_conversation_on_the_session(): void
    {
        $session = TherapySession::factory()->create();
        Sanctum::actingAs(\App\Models\User::factory()->create());

        $this->postJson("/api/v2/user/bookings/{$session->id}/message")
            ->assertStatus(404);
    }
}
