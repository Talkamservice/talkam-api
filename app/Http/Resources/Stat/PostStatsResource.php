<?php

namespace App\Http\Resources\Stat;

use Illuminate\Http\Resources\Json\JsonResource;

class PostStatsResource extends JsonResource
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
            "comments" => $this->comments,
            "likes" => $this->likes,
            "dislikes" => $this->dislikes,
            "shares" => $this->shares,
            "impressions" => $this->impressions,
            "engagements" => ($this->likes + $this->dislikes + $this->shares + $this->clicks + $this->comments),
            "followers" => $this->followers,
            "profile_visits" => $this->profile_visits,
            "clicks" => $this->clicks,
            "min_time_spent" => $this->min_time_spent,
            "max_time_spent" => $this->max_time_spent,
            "created_at" => formatDate($this->created_at),
        ];
    }
}
