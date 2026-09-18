<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcasts synchronously (ShouldBroadcastNow, not ShouldBroadcast) — a
 * queued broadcast needs a `jobs` table and a running queue worker, neither
 * of which this app has; and for a chat message, "queued" and "realtime"
 * are in direct tension anyway.
 */
class ReceiveMessage implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $conversationId;
    public $userId;

    /**
     * Create a new event instance.
     */
    public function __construct($message, $conversationId, $userId)
    {
        $this->message = $message;
        $this->conversationId = $conversationId;
        $this->userId = $userId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.' . $this->conversationId)
        ];
    }

    public function broadcastAs()
    {
        return "receive-message.{$this->userId}";
    }

    public function broadcastWith()
    {
        return [
            'data' => $this->message,
        ];
    }
}
