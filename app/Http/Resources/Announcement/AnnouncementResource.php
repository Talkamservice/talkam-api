<?php

namespace App\Http\Resources\Announcement;

use App\Constants\Account\User\UserConstants;
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
        if ($this->audience === 'Group_Admins') {
            // Return users belonging to a group where the role is 'Admin'
            return Group::whereHas('members', function ($query) {
                $query->whereIn('role', [UserConstants::OWNER, UserConstants::ADMIN]);
            })->with(['members' => function ($query) {
                $query->whereIn('role', [UserConstants::OWNER, UserConstants::ADMIN]);
            }])->get()->pluck('members')->flatten();
        } elseif ($this->audience === 'General') {
            // Return all users (including those with and without a group)
            return User::all();
        } else {
            return collect([]);
        }
    }
}
