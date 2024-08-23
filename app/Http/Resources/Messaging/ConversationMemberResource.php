<?php

namespace App\Http\Resources\Messaging;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationMemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "id" => $this->user->id,
            "name" => $this->user->full_name,
            "username" => $this->user->username,
            "email" => $this->user->email,
            "avatar" => $this->user->avatar,
            "status" => $this->status
        ];
    }
}
