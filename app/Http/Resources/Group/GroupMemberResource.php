<?php

namespace App\Http\Resources\Group;

use App\Constants\General\StatusConstants;
use App\Http\Resources\Users\UserResource;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupMemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */

    public function toArray($request)
    {
        return [
            "id" => $this->id,
            "role" => $this->role,
            "status" => $this->status,
            "suspension_ends_at" => $this->suspension_end,
            "is_suspended" => $this->isSuspended(),
            "is_banned" => $this?->status == StatusConstants::BANNED,
            "group" => GroupResource::custom($this->group),
            "user" => UserResource::custom($this->user),
            "created_at" => formatDate($this->created_at),
            "updated_at" => formatDate($this->updated_at)
        ];
    }

}
