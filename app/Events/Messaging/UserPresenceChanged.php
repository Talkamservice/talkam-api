<?php

namespace App\Events\Messaging;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserPresenceChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $userId, public string $status)
    {
    }

    public function broadcastOn(): array
    {
        return [new Channel('presence-user.' . $this->userId)];
    }

    public function broadcastAs()
    {
        return "user-presence-changed";
    }

    public function broadcastWith(): array
    {
        return ["user_id" => $this->userId, "status" => $this->status];
    }
}
