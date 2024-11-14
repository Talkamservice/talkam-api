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
            "avatar" => $this->avatar,
            "name" => $this->full_name,
            "username" => $this->username,
            "role" => $this->role,
            "email" => (string) $this->email,
        ];
    }
}
