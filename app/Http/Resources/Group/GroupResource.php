<?php

namespace App\Http\Resources\Group;

use App\Http\Resources\PostCategory\PostCategoryResource;
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
        return [
            "id" => $this->id,
            "name" => $this->name,
            "uuid" => $this->uuid,
            "status" => $this->status,
            "group_access" => $this->group_access,
            "image" => $this->image,
            "total_members" => $this->members?->count(),
            "category" => PostCategoryResource::make($this->whenLoaded("category", $this->category)),
            "description" => $this->description,
            "about" => $this->about,
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
