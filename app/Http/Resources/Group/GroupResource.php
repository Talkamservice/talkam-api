<?php

namespace App\Http\Resources\Group;

use App\Http\Resources\Guideline\GuidelineResource;
use App\Http\Resources\PostCategory\PostCategoryResource;
use App\Http\Resources\Users\UserResource;
use App\Models\GroupMember;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */

    public function toArray($request)
    {
        $is_following = GroupMember::where([
            "group_id" => $this->id,
            "user_id" => auth("sanctum")->id(),
        ])->exists();

        return [
            "id" => $this->id,
            "name" => $this->name,
            "uuid" => $this->uuid,
            "status" => $this->status,
            "group_access" => $this->group_access,
            "image" => $this->image,
            "is_following" => $is_following,
            "total_members" => $this->members?->count(),
            "category" => PostCategoryResource::make($this->whenLoaded("category", $this->category)),
            "guidelines" => GuidelineResource::collection($this->whenLoaded("guidelines", $this->guidelines)),
            "description" => $this->description,
            "owner" => !empty($this->creator) ? UserResource::custom($this->creator) : null,
            "about" => $this->about,
            "created_at" => formatDate($this->created_at),
            "updated_at" => formatDate($this->updated_at)
        ];
    }

    public static function custom($model)
    {
        return [
            "id" => $model->id,
            "name" => $model->name,
            "uuid" => $model->uuid,
            "status" => $model->status,
            "image" => $model->image,
        ];
    }
}
