<?php

namespace App\Http\Resources\Group;

use App\Http\Resources\User\UserResource;
use App\Http\Resources\Coperate\Group\GroupResource;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupExecutiveResource extends JsonResource
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
            "group" => GroupResource::make($this->whenLoaded("group", $this->group)),
            "user" => UserResource::make($this->whenLoaded("user", $this->user)),
            "role" => $this->role,
            "status" => $this->status,
        ];
    }
}
