<?php

namespace App\Http\Resources\Announcement;

use App\Http\Resources\Users\UserResource;
use App\Models\Group;
use App\Models\User;
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
            "audience" => [
                "type" => $this->audience,
                "data" => $this->getTargetedUsers()
            ],
            "status" => $this->status,
            "published_at" => formatDate($this->published_at ?? null),
            "created_at" => formatDate($this->created_at),
            "updated_at" => formatDate($this->updated_at)
        ];
    }

    protected function getTargetedUsers()
    {
        if ($this->audience === 'Group') {
            // Return users belonging to a group
            return Group::whereHas('members')->get();
        } elseif ($this->audience === 'Public') {
            // Return all users (including those with and without a group)
            return User::all();
        } else {
            return collect([]);
        }
    }
}
