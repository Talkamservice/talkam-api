<?php

namespace Tests\Feature\V2\Messaging;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessageSearchTest extends TestCase
{
    use RefreshDatabase, MessagingTestHelper;

    public function test_search_scoped_to_own_conversations_only(): void
    {
        [$mine, $a, $b] = $this->conversationBetween();
        $this->messageFrom($b, $a, $mine, ["message" => "the secret phrase"]);

        // The same phrase in a conversation the caller is NOT in.
        [$foreign, $x, $y] = $this->conversationBetween();
        $this->messageFrom($x, $y, $foreign, ["message" => "the secret phrase elsewhere"]);

        Sanctum::actingAs($a);
        $rows = $this->getJson("/api/v2/user/messaging/messages/search?q=secret")
            ->assertStatus(200)->json("data.data");

        $this->assertCount(1, $rows);
        $this->assertSame($mine->id, $rows[0]["conversation_id"]);
    }

    public function test_min_query_length_and_filters_narrow_results(): void
    {
        [$conversation, $a, $b] = $this->conversationBetween();
        $this->messageFrom($b, $a, $conversation, ["message" => "hello world", "message_type" => "text"]);
        $this->messageFrom($a, $b, $conversation, ["message" => "hello again", "message_type" => "text"]);

        Sanctum::actingAs($a);
        $this->getJson("/api/v2/user/messaging/messages/search?q=h")->assertStatus(422);

        $all = $this->getJson("/api/v2/user/messaging/messages/search?q=hello")->json("data.data");
        $this->assertCount(2, $all);

        $filtered = $this->getJson("/api/v2/user/messaging/messages/search?q=hello&sender_id={$b->id}")
            ->json("data.data");
        $this->assertCount(1, $filtered);
        $this->assertSame($b->id, $filtered[0]["sender_id"]);
    }

    public function test_guest_unauthorized(): void
    {
        $this->getJson("/api/v2/user/messaging/messages/search?q=test")->assertStatus(401);
    }
}
