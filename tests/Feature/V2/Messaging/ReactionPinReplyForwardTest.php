<?php

namespace Tests\Feature\V2\Messaging;

use App\Models\Message;
use App\Models\MessageReaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReactionPinReplyForwardTest extends TestCase
{
    use RefreshDatabase, MessagingTestHelper;

    public function test_reaction_added_and_duplicate_returns_friendly_error(): void
    {
        [$conversation, $a, $b] = $this->conversationBetween();
        $message = $this->messageFrom($a, $b, $conversation);

        Sanctum::actingAs($b);
        $this->postJson("/api/v2/user/messaging/messages/add-reaction", [
            "message_id" => $message->id,
            "reaction" => "❤️",
        ])->assertStatus(200);

        // Duplicate → friendly 4xx, not Fundnai's raw 500.
        $this->postJson("/api/v2/user/messaging/messages/add-reaction", [
            "message_id" => $message->id,
            "reaction" => "❤️",
        ])->assertStatus(403)->assertJson(["success" => false]);

        $this->assertSame(1, MessageReaction::count());
    }

    public function test_reaction_length_capped(): void
    {
        [$conversation, $a, $b] = $this->conversationBetween();
        $message = $this->messageFrom($a, $b, $conversation);

        Sanctum::actingAs($b);
        $this->postJson("/api/v2/user/messaging/messages/add-reaction", [
            "message_id" => $message->id,
            "reaction" => str_repeat("x", config("v2.messaging.reaction_max_length") + 1),
        ])->assertStatus(422);
    }

    public function test_remove_reaction_and_404_when_absent(): void
    {
        [$conversation, $a, $b] = $this->conversationBetween();
        $message = $this->messageFrom($a, $b, $conversation);
        MessageReaction::create(["message_id" => $message->id, "user_id" => $b->id, "reaction" => "👍"]);

        Sanctum::actingAs($b);
        $this->postJson("/api/v2/user/messaging/messages/remove-reaction", [
            "message_id" => $message->id,
            "reaction" => "👍",
        ])->assertStatus(200);
        $this->assertSame(0, MessageReaction::count());

        $this->postJson("/api/v2/user/messaging/messages/remove-reaction", [
            "message_id" => $message->id,
            "reaction" => "👍",
        ])->assertStatus(404);
    }

    public function test_pin_unpin_member_only_and_pinned_filter(): void
    {
        [$conversation, $a, $b] = $this->conversationBetween();
        $pinned = $this->messageFrom($a, $b, $conversation);
        $this->messageFrom($a, $b, $conversation);

        Sanctum::actingAs($b);
        $this->postJson("/api/v2/user/messaging/messages/pin", ["message_id" => $pinned->id])
            ->assertStatus(200);

        $this->assertTrue($pinned->refresh()->is_pinned);
        $this->assertSame($b->id, $pinned->pinned_by);

        $ids = collect($this->getJson("/api/v2/user/messaging/messages/list?conversation_id={$conversation->id}&pinned=1")
            ->json("data.data"))->pluck("id");
        $this->assertEquals([$pinned->id], $ids->all());

        // Non-member cannot pin.
        Sanctum::actingAs(\App\Models\User::factory()->create());
        $this->postJson("/api/v2/user/messaging/messages/pin", ["message_id" => $pinned->id])
            ->assertStatus(404);
    }

    public function test_reply_increments_reply_count(): void
    {
        Notification::fake();
        [$conversation, $a, $b] = $this->conversationBetween();
        $parent = $this->messageFrom($a, $b, $conversation);

        Sanctum::actingAs($b);
        $this->postJson("/api/v2/user/messaging/messages/reply", [
            "conversation_id" => $conversation->id,
            "message" => "Replying to you",
            "replied_to_message_id" => $parent->id,
        ])->assertStatus(200);

        $this->assertSame(1, $parent->refresh()->reply_count);
        $this->assertDatabaseHas("messages", [
            "message" => "Replying to you",
            "replied_to_message_id" => $parent->id,
        ]);
    }

    public function test_forward_marks_forwarded_lineage(): void
    {
        Notification::fake();
        [$conversation_one, $a, $b] = $this->conversationBetween();
        $original = $this->messageFrom($b, $a, $conversation_one, ["message" => "Forward me"]);
        [$conversation_two] = $this->conversationBetween($a);

        Sanctum::actingAs($a);
        $this->postJson("/api/v2/user/messaging/messages/forward", [
            "message_id" => $original->id,
            "conversation_id" => $conversation_two->id,
        ])->assertStatus(200);

        $forwarded = Message::where("conversation_id", $conversation_two->id)->first();
        $this->assertTrue($forwarded->is_forwarded);
        $this->assertSame($original->id, $forwarded->forwarded_from_message_id);
        $this->assertSame("Forward me", $forwarded->message);
    }
}
