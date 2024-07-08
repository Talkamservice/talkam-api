<?php

namespace App\Http\Resources\Post;

use App\Http\Resources\PostCategory\PostCategoryResource;
use App\Http\Resources\Users\UserResource;
use App\Models\UserPostReaction;
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
        $user_reaction = UserPostReaction::where(["post_id" => $this->id, "user_id" => auth()->id()])->first();

        return [
            "id" => $this->id,
            "title" => $this->title,
            "body" => $this->body,
            "type" => $this->type,
            "uuid" => $this->uuid,
            "category" => PostCategoryResource::make($this->whenLoaded("category", $this->category)),
            "user" => !empty($this->user) ? UserResource::custom($this->user) : null,
            "can_comment" => $this->can_comment,
            "is_anonymous" => $this->is_anonymous,
            "tags" => $this->tags,
            "views_count" => $this->views_count,
            "comments_count" => $this->comments?->count(),
            "status" => $this->status,
            "publish_at" => $this->publish_at,
            "attachments" => PostAttachmentResource::collection($this->whenLoaded("attachments", $this->attachments)),
            "polls" => PostPollResource::collection($this->whenLoaded("polls", $this->polls)),
            "reaction" => !empty($user_reaction) ? PostReactionResource::make($user_reaction) : null,
            "created_at" => formatDate($this->created_at),
            "updated_at" => formatDate($this->updated_at)
        ];
    }
}
