<?php

namespace App\Http\Resources\Promotion;

use App\Http\Resources\Location\CountryResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromotionPricingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "id" => $this->id,
            "amount" => $this->amount,
            "impressions" => $this->impressions,
            "max_daily_amount" => $this->max_daily_amount,
            'currency' => [
                "name" => $this->currency->name,
                "symbol" => $this->currency->symbol,
                "short_name" => $this->currency->short_name,
            ],
            "country" => !empty($this->country) ? CountryResource::make($this->whenLoaded("country", $this->country)) : null,
            "status" => $this->status,
        ];
    }
}
