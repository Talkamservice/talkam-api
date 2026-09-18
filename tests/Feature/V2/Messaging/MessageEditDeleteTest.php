<?php

namespace Tests\Feature\V2\Messaging;

use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessageEditDeleteTest extends TestCase
{
    use RefreshDatabase, MessagingTestHelper;

    public function test_sender_edits_within_window_writes_history(): void
    {
        [$conversation, $a, $b] = $this->conversationBetween();
        $message = $this->messageFrom($a, $b, $conversation, ["message" => "Original text"]);

        Sanctum::actingAs($a);
        $this->postJson("/api/v2/user/messaging/messages/edit", [
            "message_id" => $message->id,
            "message" => "Edited text",
        ])->assertStatus(200);

        $message->refresh();
        $this->assertSame("Edited text", $message->message);
        $this->assertSame("Original text", $message->original_message);
        $this->assertNotNull($message->edited_at);
        $this->assertDatabaseHas("message_edits", [
            "message_id" => $message->id,
            "original_content" => "Original text",
            "new_content" => "Edited text",
        ]);
    }

    public function test_edit_after_window_rejected_and_config_driven(): void
    {
        [$conversation, $a, $b] = $this->conversationBetween();
        $message = $this->messageFrom($a, $b, $conversation);

        Sanctum::actingAs($a);
        $this->travel(config("v2.messaging.edit_window_minutes") + 1)->minutes();

        $this->postJson("/api/v2/user/messaging/messages/edit", [
            "message_id" => $message->id,
            "message" => "Too late",
        ])->assertStatus(422);

        // Config override: 0 = unlimited.
        config(["v2.messaging.edit_window_minutes" => 0]);
        $this->postJson("/api/v2/user/messaging/messages/edit", [
            "message_id" => $message->id,
            "message" => "Now allowed",
        ])->assertStatus(200);
    }

    public function test_non_sender_cannot_edit(): void
    {
        [$conversation, $a, $b] = $this->conversationBetween();
        $message = $this->messageFrom($a, $b, $conversation);

        Sanctum::actingAs($b);
        $this->postJson("/api/v2/user/messaging/messages/edit", [
            "message_id" => $message->id,
            "message" => "Hijack",
        ])->assertStatus(403);
    }

    public function test_delete_for_everyone_soft_deletes_and_blanks_content(): void
    {
        [$conversation, $a, $b] = $this->conversationBetween();
        $message = $this->messageFrom($a, $b, $conversation, ["message" => "Sensitive"]);

        Sanctum::actingAs($a);
        $this->deleteJson("/api/v2/user/messaging/messages/{$message->id}?type=for_everyone")
            ->assertStatus(200);

        $row = Message::withTrashed()->find($message->id);
        $this->assertNotNull($row->deleted_at);
        $this->assertNull($row->message);
        $this->assertSame("for_everyone", $row->delete_type);
        $this->assertSame($a->id, $row->deleted_by);
    }

    public function test_non_sender_cannot_delete_for_everyone(): void
    {
        [$conversation, $a, $b] = $this->conversationBetween();
        $message = $this->messageFrom($a, $b, $conversation);

        Sanctum::actingAs($b);
        $this->deleteJson("/api/v2/user/messaging/messages/{$message->id}?type=for_everyone")
            ->assertStatus(403);

        $this->assertNull($message->refresh()->deleted_at);
    }

    public function test_delete_for_me_hides_only_for_caller(): void
    {
        [$conversation, $a, $b] = $this->conversationBetween();
        $message = $this->messageFrom($a, $b, $conversation);

        Sanctum::actingAs($b);
        $this->deleteJson("/api/v2/user/messaging/messages/{$message->id}?type=for_me")
            ->assertStatus(200);

        $b_ids = collect($this->getJson("/api/v2/user/messaging/messages/list?conversation_id={$conversation->id}")
            ->json("data.data"))->pluck("id");
        $this->assertFalse($b_ids->contains($message->id));

        Sanctum::actingAs($a);
        $a_ids = collect($this->getJson("/api/v2/user/messaging/messages/list?conversation_id={$conversation->id}")
            ->json("data.data"))->pluck("id");
        $this->assertTrue($a_ids->contains($message->id));
    }
}
