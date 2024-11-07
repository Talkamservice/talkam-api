<?php

namespace App\Http\Resources\Post;

use App\Constants\Post\PostConstants;
use App\Http\Resources\Group\GroupResource;
use App\Http\Resources\PostCategory\PostCategoryResource;
use App\Http\Resources\Promotion\PromotionResource;
use App\Http\Resources\Users\UserResource;
use App\Models\PostReport;
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
        $user_reaction = UserPostReaction::where(["post_id" => $this->id, "user_id" => auth("sanctum")->id()])->first();
        $likes = UserPostReaction::where(["post_id" => $this->id, "action" => PostConstants::LIKE])->count();

        $is_reported = PostReport::where([
            "user_id" => auth("sanctum")->id(),
            "post_id" => $this->id,
        ])->exists();

        $enabled_notification = $this->threadNotifications?->where("user_id", auth("sanctum")->id());

        return [
            "id" => $this->id,
            "title" => $this->title,
            "body" => $this->body,
            "type" => $this->type,
            "uuid" => $this->uuid,
            "category" => PostCategoryResource::make($this->whenLoaded("category", $this->category)),
            "user" => ((!empty($this->user)) && ($this->is_anonymous != 1)) ? UserResource::custom($this->user) : null,
            "group" => !empty($this->group) ? GroupResource::custom($this->group) : null,
            "can_comment" => $this->can_comment,
            "is_anonymous" => $this->is_anonymous,
            "tags" => is_string($this->tags) ? json_decode($this->tags, true) : $this->tags,
            "is_reported" => $is_reported,
            "views_count" => $this->views_count,
            "comments_count" => $this->comments()->topLevel()->count(),  // Counts only top-level comments
            "likes_count" => $likes,
            "status" => $this->status,
            "publish_at" => $this->publish_at,
            "enabled_notification" => $enabled_notification?->isNotEmpty(),
            "promotion" => !empty($this->activePromotion()) ? true : false,
            "attachments" => PostAttachmentResource::collection($this->whenLoaded("attachments", $this->attachments)),
            "polls" => PostPollResource::collection($this->whenLoaded("polls", $this->polls)),
            "reaction" => !empty($user_reaction) ? PostReactionResource::make($user_reaction) : null,
            "created_at" => formatDate($this->created_at),
            "updated_at" => formatDate($this->updated_at)
        ];
    }

    public static function custom($model)
    {
        return [
            "id" => $model->id,
            "title" => $model->title,
            "type" => $model->type,
            "uuid" => $model->uuid,
            "can_comment" => $model->can_comment,
            "is_anonymous" => $model->is_anonymous,
            "tags" => $model->tags,
            "views_count" => $model->views_count,
            "status" => $model->status,
            "publish_at" => $model->publish_at,
            "created_at" => formatDate($model->created_at),
            "user" => ((!empty($model->user)) && ($model->is_anonymous != 1)) ? UserResource::custom($model->user) : null,
        ];
    }
}
