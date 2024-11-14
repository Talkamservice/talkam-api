<?php

namespace App\Http\Resources\Messaging;

use App\Constants\General\StatusConstants;
use App\Http\Resources\Users\UserResource;
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
            "number_of_unread" => $this->messages()->where('receiver_id', auth()->id())->where('read', false)->count(),
            "notification_status" => $this->notification_status,
            "is_anonymous" => $this->is_anonymous,
            "requested_by" => UserResource::custom($this->user),
            "user_is_banned" => $other_member?->user?->status == StatusConstants::BANNED,
            "user_blocked" => isBlocked(auth()->id(), $other_member?->user_id),
            "i_am_blocked" => isBlocked($other_member?->user_id, auth()->id()),
            "status" => $this->status
        ];
    }
}
