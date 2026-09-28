<?php

namespace App\Events\Messaging;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationSeen implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $conversationId, public array $payload = [])
    {
    }

    public function broadcastOn(): array
    {
        return [new Channel('private-conversation.' . $this->conversationId)];
    }

    public function broadcastAs()
    {
        return "conversation-seen";
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
