<?php

namespace Tests\Feature\V2\Messaging;

use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class V1MessagingRegressionTest extends TestCase
{
    use RefreshDatabase, MessagingTestHelper;

    public function test_v1_send_and_list_unchanged(): void
    {
        Notification::fake();
        [$conversation, $a, $b] = $this->conversationBetween();

        Sanctum::actingAs($a);
        $this->postJson("/api/v1/user/messaging/messages/send", [
            "conversation_id" => $conversation->id,
            "receiver_id" => $b->id,
            "message" => "Via the v1 lane",
        ])->assertStatus(200)->assertJson(["success" => true]);

        $this->assertDatabaseHas("messages", ["message" => "Via the v1 lane"]);

        $this->getJson("/api/v1/user/messaging/messages/list?conversation_id={$conversation->id}")
            ->assertStatus(200)->assertJson(["success" => true]);
    }

    public function test_v1_delete_now_soft_but_client_behavior_identical(): void
    {
        Notification::fake();
        [$conversation, $a, $b] = $this->conversationBetween();
        $message = $this->messageFrom($a, $b, $conversation);

        Sanctum::actingAs($a);
        $this->deleteJson("/api/v1/user/messaging/messages/delete/{$message->id}")
            ->assertStatus(200);

        // Row retained (soft), absent from v1 lists — client-visible
        // behavior identical.
        $this->assertNotNull(Message::withTrashed()->find($message->id)->deleted_at);
        $ids = collect($this->getJson("/api/v1/user/messaging/messages/list?conversation_id={$conversation->id}")
            ->json("data.data"))->pluck("id");
        $this->assertFalse($ids->contains($message->id));
    }
}
