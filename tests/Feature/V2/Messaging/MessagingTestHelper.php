<?php

namespace Tests\Feature\V2\Messaging;

use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\Message;
use App\Models\User;

trait MessagingTestHelper
{
    /**
     * An active conversation between two fresh users.
     * Returns [Conversation, User $a, User $b].
     */
    protected function conversationBetween(?User $a = null, ?User $b = null): array
    {
        $a = $a ?? User::factory()->create();
        $b = $b ?? User::factory()->create();

        $conversation = Conversation::factory()->create(["user_id" => $a->id]);
        ConversationMember::factory()->create(["conversation_id" => $conversation->id, "user_id" => $a->id]);
        ConversationMember::factory()->create(["conversation_id" => $conversation->id, "user_id" => $b->id]);

        return [$conversation, $a, $b];
    }

    protected function messageFrom(User $sender, User $receiver, Conversation $conversation, array $overrides = []): Message
    {
        return Message::factory()->create(array_merge([
            "conversation_id" => $conversation->id,
            "sender_id" => $sender->id,
            "receiver_id" => $receiver->id,
        ], $overrides));
    }
}
