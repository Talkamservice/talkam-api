<?php

namespace App\Http\Resources\Promotion;

use App\Http\Resources\Group\GroupResource;
use App\Http\Resources\Location\CountryResource;
use App\Http\Resources\Post\PostResource;
use App\Http\Resources\Stat\PostStatsResource;
use App\Http\Resources\Users\UserResource;
use App\Models\Country;
use Illuminate\Http\Resources\Json\JsonResource;

class PromotionResource extends JsonResource
{
    public function __construct(public $resource, public $show_countries_stats = true) {}
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */

    public function toArray($request)
    {
        $countries = Country::whereRelation("promotionLocations", "promotion_id", $this->id)->get();
        return [
            "id" => $this->id,
            "user" => !empty($this->user) ? UserResource::custom($this->user) : null,
            "post" => !empty($this->post) ? PostResource::make($this->post) : null,
            "group" => !empty($this->group) ? GroupResource::make($this->group) : null,
            "country" => $countries->isNotEmpty() ? CountryResource::collection($countries) : null,
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
            "stats" => PostStatsResource::make($this->stat(), $countries, $this->show_countries_stats),
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
