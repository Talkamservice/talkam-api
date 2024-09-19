<?php

namespace App\Http\Resources\Group;

use App\Constants\General\StatusConstants;
use App\Http\Resources\Guideline\GuidelineResource;
use App\Http\Resources\PostCategory\PostCategoryResource;
use App\Http\Resources\Users\UserResource;
use App\Models\GroupMember;
use App\Models\GroupReport;
use Illuminate\Http\Resources\Json\JsonResource;
use Predis\Response\Status;

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
        $group_member = GroupMember::where([
            "group_id" => $this->id,
            "user_id" => auth("sanctum")->id(),
        ])->first();

        $group_reported = GroupReport::where([
            "group_id" => $this->id,
            "user_id" => auth("sanctum")->id(),
            "status" => StatusConstants::PENDING
        ])->latest()->first();

        return [
            "id" => $this->id,
            "name" => $this->name,
            "uuid" => $this->uuid,
            "status" => $this->status,
            "group_access" => $this->group_access,
            "image" => $this->image,
            "is_following" => !empty($group_member) && ($group_member?->status == StatusConstants::ACTIVE),
            "user_role" => $group_member?->role,
            "has_requested" => in_array($group_member?->status, [StatusConstants::PENDING, StatusConstants::DECLINED]),
            "is_suspended" => $group_member?->status == StatusConstants::SUSPENDED,
            "is_banned" => $group_member?->status == StatusConstants::BANNED,
            "is_reported" => !empty($group_reported),
            "group_member_status" => $group_member?->status ?? null,
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
