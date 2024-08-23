<?php

namespace App\Http\Resources\Messaging;

use App\Http\Resources\Users\UserResource;
use App\Services\User\BlockUserService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $other_member = $this->members()->whereNot("user_id", auth()->id())->first();
        return [
            "id" => $this->id,
            "members" => ConversationMemberResource::collection($this->members),
            "last_message" => MessageResource::make($this->messages()->latest()->first()),
            "number_of_unread" => $this->messages()->where('receiver_id', $this->receiver_id)->where('read', false)->count(),
            "notification_status" => $this->notification_status,
            "is_anonymous" => $this->is_anonymous,
            "requested_by" => UserResource::custom($this->user),
            "user_blocked" => (new BlockUserService)->isBlocked(auth()->id(), $other_member?->user_id),
            "status" => $this->status
        ];
    }
}
