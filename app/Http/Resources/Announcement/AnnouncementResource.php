<?php

namespace App\Http\Resources\Announcement;

use App\Http\Resources\Users\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "id" => $this->id,
            "user" =>  UserResource::custom($this->user),
            "banner_image" => $this->bannerUrl(),
            "title" => $this->title,
            "description" => $this->body,
            "audience" => $this->audience,
            "status" => $this->status,
            "published_at" => formatDate($this->published_at ?? null),
            "created_at" => formatDate($this->created_at),
            "updated_at" => formatDate($this->updated_at)
        ];
    }
}
