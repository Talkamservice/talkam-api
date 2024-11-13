<?php

namespace App\Http\Resources\Promotion;

use App\Http\Resources\Group\GroupResource;
use App\Http\Resources\Post\PostResource;
use App\Http\Resources\Post\TrendingResource;
use App\Http\Resources\Stat\PostStatsResource;
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
            "user" => !empty($this->user) ? UserResource::custom($this->user) : null,
            "post" => !empty($this->post) ? PostResource::make($this->post) : null,
            "group" => !empty($this->group) ? GroupResource::make($this->group) : null,
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
            "status" => $this->status,
            "expires_at" => formatDate($this->expires_at),
            "stats" => PostStatsResource::make($this->stat),
            "created_at" => formatDate($this->created_at),
            "updated_at" => formatDate($this->updated_at)
        ];
    }

    public static function custom($model)
    {
        return [
            "id" => $model->id,
            "min_age" => $model->min_age,
            "max_age" => $model->max_age,
            "gender" => $model->gender,
            "daily_budget" => $model->daily_budget,
            "frequency" => $model->frequency,
            "duration" => $model->duration,
            "estimated_reach" => $model->estimated_reach,
            "total_reach" => $model->total_reach,
            "created_at" => formatDate($model->created_at),
            "updated_at" => formatDate($model->updated_at)
        ];
    }
}
