<?php

namespace Tests\Feature\V2\Messaging;

use App\Events\Messaging\MessageRead;
use App\Models\Message;
use App\Models\UserPrivacySetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReadReceiptTest extends TestCase
{
    use RefreshDatabase, MessagingTestHelper;

    public function test_listing_marks_only_inbound_messages_read(): void
    {
        Event::fake([MessageRead::class]);
        [$conversation, $a, $b] = $this->conversationBetween();
        $inbound = $this->messageFrom($a, $b, $conversation);
        $own = $this->messageFrom($b, $a, $conversation);

        Sanctum::actingAs($b);
        $this->getJson("/api/v2/user/messaging/messages/list?conversation_id={$conversation->id}")
            ->assertStatus(200);

        // v1 bug #2 fixed: only inbound (to B) marked; B's own sent
        // message untouched.
        $this->assertNotNull($inbound->refresh()->read_at);
        $this->assertNull($own->refresh()->read_at);
        Event::assertDispatched(MessageRead::class);
    }

    public function test_read_event_suppressed_when_read_receipts_off(): void
    {
        Event::fake([MessageRead::class]);
        [$conversation, $a, $b] = $this->conversationBetween();
        $this->messageFrom($a, $b, $conversation);
        UserPrivacySetting::create(["user_id" => $b->id, "read_receipts" => false]);

        Sanctum::actingAs($b);
        $this->getJson("/api/v2/user/messaging/messages/list?conversation_id={$conversation->id}");

        // Marked internally, but no receipt event toward the counterpart.
        $this->assertNotNull(Message::first()->read_at);
        Event::assertNotDispatched(MessageRead::class);

        // And the sender's view omits the read state.
        Sanctum::actingAs($a);
        $payload = $this->getJson("/api/v2/user/messaging/messages/list?conversation_id={$conversation->id}")
            ->json("data.data");
        $this->assertNull($payload[0]["read_at"]);
        $this->assertNull($payload[0]["read"]);
    }

    public function test_bulk_mark_as_read(): void
    {
        Event::fake([MessageRead::class]);
        [$conversation, $a, $b] = $this->conversationBetween();
        $this->messageFrom($a, $b, $conversation);
        $this->messageFrom($a, $b, $conversation);

        Sanctum::actingAs($b);
        $this->postJson("/api/v2/user/messaging/messages/bulk-mark-as-read", [
            "conversation_id" => $conversation->id,
        ])->assertStatus(200);

        $this->assertSame(0, Message::whereNull("read_at")->where("receiver_id", $b->id)->count());
    }

    public function test_seen_updates_own_member_row_only(): void
    {
        [$conversation, $a, $b] = $this->conversationBetween();
        $message = $this->messageFrom($a, $b, $conversation);

        Sanctum::actingAs($b);
        $this->postJson("/api/v2/user/messaging/conversations/seen", [
            "conversation_id" => $conversation->id,
        ])->assertStatus(200);

        $this->assertDatabaseHas("conversation_members", [
            "conversation_id" => $conversation->id,
            "user_id" => $b->id,
            "last_seen_message_id" => $message->id,
        ]);
        $this->assertDatabaseHas("conversation_members", [
            "conversation_id" => $conversation->id,
            "user_id" => $a->id,
            "last_seen_message_id" => null,
        ]);
    }
}
