<?php

namespace App\Http\Resources\Notification;

use App\Http\Resources\General\FileResource;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
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
            "title" => $this->data["title"] ?? "",
            "message" => strip_tags($this->data["message"]) ?? "",
            "type" => $this->data["type"] ?? "",
            "data_id" => $this->data["data"]["id"] ?? null,
            "extra" => $this->data["extra"] ?? [],
            "read_at" => formatDate($this->read_at),
            "created_at" => formatDate($this->created_at),
        ];
    }
}
