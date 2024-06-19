<?php

namespace App\Http\Resources\Users;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            "id" => (int) $this->id,
            "avatar" => $this->avatar,
            "name" => $this->full_name,
            "email" => (string) $this->email,
            "role" => $this->role,
            "username" => (string) $this->username,
            "status" => (string) $this->status,
            "created_at" => formatDate($this->created_at),
            "updated_at" => formatDate($this->updated_at)
        ];
    }

    public static function custom($model)
    {
        return [
            "id" => (int) $model->id,
            "avatar" => $model->avatarUrl(),
            "name" => $model->name,
            "email" => (string) $model->email,
        ];
    }
}
