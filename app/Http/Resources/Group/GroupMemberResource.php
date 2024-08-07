<?php

namespace App\Http\Resources\Group;

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
            "group" => GroupResource::custom($this->group),
            "user" => UserResource::custom($this->user),
            "created_at" => $this->created_at,
        ];
    }
}
