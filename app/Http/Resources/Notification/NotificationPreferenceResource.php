<?php

namespace App\Http\Resources\Notification;

use App\Http\Resources\Group\GroupResource;
use App\Http\Resources\Users\UserResource;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationPreferenceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public $resource;

    public function toArray($request)
    {
        return [
            "id" => $this->id,
            "talkam_news" => $this->talkam_news,
            "talkam_research" => $this->talkam_research,
            "user_activities" => $this->user_activities,
            "comments" => $this->comments,
            "moderation_activities" => $this->moderation_activities,
            "user" => UserResource::custom($this->user),
        ];
    }
}
