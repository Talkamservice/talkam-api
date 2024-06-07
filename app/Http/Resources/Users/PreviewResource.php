<?php

namespace App\Http\Resources\Users;

use Illuminate\Http\Resources\Json\JsonResource;

class PreviewResource extends JsonResource
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
            "avatar" => $this->avatarUrl(),
            "name" => $this->name,
            "role" => $this->role,
            "avatar_background_color" => $this->avatar_background_color,
            "email" => (string) $this->email,
        ];
    }
}
