<?php

namespace App\Http\Resources\Post;

use App\Http\Resources\Users\UserResource;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
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
            "title" => $this->title,
            "body" => $this->body,
            "type" => $this->type,
            "uuid" => $this->uuid,
            "user" => UserResource::make($this->whenLoaded("user", $this->user)),
            "can_comment" => $this->can_comment,
            "is_anonymous" => $this->is_anonymous,
            "views_count" => $this->views_count,
            "status" => $this->status,
            "publish_at" => $this->publish_at,
            "attachments" => PostAttachmentResource::collection($this->whenLoaded("attachments", $this->attachments)),
            "polls" => PostPollResource::collection($this->whenLoaded("polls", $this->polls)),
            "created_at" => formatDate($this->created_at),
            "updated_at" => formatDate($this->updated_at)
        ];
    }
}
