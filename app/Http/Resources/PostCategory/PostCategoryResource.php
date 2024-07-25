<?php

namespace App\Http\Resources\PostCategory;

use App\Models\UserInterest;
use Illuminate\Http\Resources\Json\JsonResource;

class PostCategoryResource extends JsonResource
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
        $interests = UserInterest::where("user_id", auth()->id())->pluck("category_id")->toArray();
        $is_following = in_array($this->id, $interests ?? []);

        return [
            "id" => $this->id,
            "name" => $this->name,
            "description" => $this->description,
            "background_image" => $this->image,
            "icon_image" => $this->icon_image,
            "is_following" => $is_following,
            "followers_count" => $this->interests->count(),
            "created_at" => formatDate($this->created_at),
            "updated_at" => formatDate($this->updated_at)
        ];
    }
}
