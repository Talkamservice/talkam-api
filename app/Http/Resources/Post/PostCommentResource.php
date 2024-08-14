<?php

namespace App\Http\Resources\Post;

use App\Constants\Post\PostConstants;
use App\Http\Resources\Users\UserResource;
use App\Models\CommentReport;
use App\Models\UserCommentReaction;
use Illuminate\Http\Resources\Json\JsonResource;

class PostCommentResource extends JsonResource
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
        $user_reaction = UserCommentReaction::where(["comment_id" => $this->id, "user_id" => auth("sanctum")->id()])->first();
        $likes = UserCommentReaction::where(["comment_id" => $this->id, "action" => PostConstants::LIKE])->count();
        $unlikes = UserCommentReaction::where(["comment_id" => $this->id, "action" => PostConstants::DISLIKE])->count();

        $is_reported = CommentReport::where([
            "user_id" => auth("sanctum")->id(),
            "comment_id" => $this->id,
            "post_id" => $this->post_id,
        ])->exists();

        $reply_to = !empty($this->repliedComment?->user) ? UserResource::custom($this->repliedComment?->user) : null;
        return [
            "id" => $this->id,
            "post" => PostResource::custom($this->post),
            "user" => !empty($this->user) ? UserResource::custom($this->user) : null,
            "comment" => $this->comment,
            "is_anonymous" => $this->is_anonymous,
            "likes" => $likes,
            "unlikes" => $unlikes,
            "is_reported" => $is_reported,
            "reply_to" => ($this->repliedComment?->is_anonymous != 1) ? $reply_to : null,
            "attachment" => $this->attachment,
            "reaction" => !empty($user_reaction) ? PostReactionResource::make($user_reaction) : null,
            "children" => self::collection($this->whenLoaded("children", $this->children)),
            "created_at" => formatDate($this->created_at),
            "updated_at" => formatDate($this->updated_at)
        ];
    }
}
