<?php

namespace App\Http\Resources\Finance\Plan;

use App\Http\Resources\General\FileResource;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanBenefitResource extends JsonResource
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
            'id' => $this->id,
            'title' => $this->title,
            'key' => $this->key,
            "created_at" => formatDate($this->created_at),
            "updated_at" => formatDate($this->updated_at)
        ];
    }
}
