<?php

namespace Tests\Feature\V2\Messaging;

use App\Models\User;
use App\Notifications\Messaging\NewMessageNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConversationStateTest extends TestCase
{
    use RefreshDatabase, MessagingTestHelper;

    public function test_mute_suppresses_notification_until_muted_until(): void
    {
        Notification::fake();
        [$conversation, $a, $b] = $this->conversationBetween();

        Sanctum::actingAs($b);
        $this->postJson("/api/v2/user/messaging/conversations/mute", [
            "conversation_id" => $conversation->id,
            "muted_until" => now()->addHour()->toDateTimeString(),
        ])->assertStatus(200)->assertJsonPath("data.is_muted", true);

        $message = $this->messageFrom($a, $b, $conversation);

        // Suppressed while muted...
        $this->assertSame([], (new NewMessageNotification($message))->via($b));
        // ...and the other member is unaffected.
        $reverse = $this->messageFrom($b, $a, $conversation);
        $this->assertNotEmpty((new NewMessageNotification($reverse))->via($a));

        // Resumes after muted_until passes.
        $this->travel(2)->hours();
        $this->assertNotEmpty((new NewMessageNotification($message))->via($b->refresh()));
    }

    public function test_archive_and_star_are_per_member(): void
    {
        [$conversation, $a, $b] = $this->conversationBetween();

        Sanctum::actingAs($a);
        $this->postJson("/api/v2/user/messaging/conversations/archive", [
            "conversation_id" => $conversation->id,
        ])->assertStatus(200);
        $this->postJson("/api/v2/user/messaging/conversations/star", [
            "conversation_id" => $conversation->id,
        ])->assertStatus(200);

        // A's default list hides the archived conversation.
        $a_ids = collect($this->getJson("/api/v2/user/messaging/conversations")->json("data.data"))->pluck("id");
        $this->assertFalse($a_ids->contains($conversation->id));
        $a_archived = collect($this->getJson("/api/v2/user/messaging/conversations?archived=1")->json("data.data"))->pluck("id");
        $this->assertTrue($a_archived->contains($conversation->id));

        // B's list is unaffected.
        Sanctum::actingAs($b);
        $b_ids = collect($this->getJson("/api/v2/user/messaging/conversations")->json("data.data"))->pluck("id");
        $this->assertTrue($b_ids->contains($conversation->id));
    }

    public function test_conversations_list_paginated_with_correct_last_message(): void
    {
        [$conversation, $a, $b] = $this->conversationBetween();
        $this->messageFrom($a, $b, $conversation, ["message" => "first"]);
        $latest = $this->messageFrom($b, $a, $conversation, ["message" => "latest one"]);

        Sanctum::actingAs($a);
        $response = $this->getJson("/api/v2/user/messaging/conversations")
            ->assertStatus(200)
            ->assertJsonStructure(["data" => ["data", "pagination_meta"]]);

        $row = collect($response->json("data.data"))->firstWhere("id", $conversation->id);
        // v1 bug #3 fixed: genuinely the latest MESSAGE.
        $this->assertSame($latest->id, $row["last_message"]["id"]);
        $this->assertSame(1, $row["unread_count"]);
    }

    public function test_pending_requests_returns_awaiting_conversations(): void
    {
        [$conversation, $a, $b] = $this->conversationBetween();
        $conversation->update(["status" => "Awaiting_Response"]);

        // Awaiting the RECEIVER's response — so B sees it, A (creator) doesn't.
        Sanctum::actingAs($b);
        $b_ids = collect($this->getJson("/api/v2/user/messaging/conversations/pending-requests")->json("data"))
            ->pluck("id");
        $this->assertTrue($b_ids->contains($conversation->id));

        Sanctum::actingAs($a);
        $a_ids = collect($this->getJson("/api/v2/user/messaging/conversations/pending-requests")->json("data"))
            ->pluck("id");
        $this->assertFalse($a_ids->contains($conversation->id));
    }

    public function test_report_validates_against_conversations_table(): void
    {
        [$conversation, $a] = $this->conversationBetween();

        Sanctum::actingAs($a);
        // Nonexistent conversation id → 422 (v1 bug #4: it validated
        // against the messages table).
        $this->postJson("/api/v2/user/messaging/conversations/report", [
            "conversation_id" => 999999,
            "reason" => "Spam",
        ])->assertStatus(422);

        $this->postJson("/api/v2/user/messaging/conversations/report", [
            "conversation_id" => $conversation->id,
            "reason" => "Spam",
        ])->assertStatus(200);

        $this->assertDatabaseHas("conversation_reports", ["conversation_id" => $conversation->id]);
    }

    public function test_non_member_cannot_use_state_endpoints(): void
    {
        [$conversation] = $this->conversationBetween();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v2/user/messaging/conversations/mute", [
            "conversation_id" => $conversation->id,
        ])->assertStatus(404);
    }
}
