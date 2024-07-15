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
            "image" => $this->image,
            "category" => PostCategoryResource::make($this->whenLoaded("category", $this->category)),
            "description" => $this->description,
            "rules" => $this->rules,
            "followers" => $this->followers
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
