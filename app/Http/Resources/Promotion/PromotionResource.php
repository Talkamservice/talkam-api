<?php

namespace App\Http\Resources\Promotion;

use App\Http\Resources\Post\PostResource;
use App\Http\Resources\Post\TrendingResource;
use App\Http\Resources\Users\UserResource;
use App\Models\TrendingTag;
use App\Models\UserInterest;
use Illuminate\Http\Resources\Json\JsonResource;

class PromotionResource extends JsonResource
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
            "user" => UserResource::make($this->whenLoaded("user", $this->user)),
            "post" => !empty($this->post) ? PostResource::custom($this->post) : null,
            "group" => !empty($this->group) ? PostResource::custom($this->group) : null,
            "state" => !empty($this->state) ? PostResource::custom($this->state) : null,
            "country" => !empty($this->country) ? PostResource::custom($this->country) : null,
            "min_age" => $this->min_age,
            "max_age" => $this->max_age,
            "gender" => $this->gender,
            "daily_budget" => $this->daily_budget,
            "frequency" => $this->frequency,
            "duration" => $this->duration,
            "estimated_reach" => $this->estimated_reach,
            "total_reach" => $this->total_reach,
            "created_at" => formatDate($this->created_at),
            "updated_at" => formatDate($this->updated_at)
        ];
    }
}
