<?php

namespace App\Http\Resources\Promotion;

use App\Helpers\MethodsHelper;
use App\Http\Resources\Location\CountryResource;
use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromotionPricingResource extends JsonResource
{
    public function __construct(public $resource, public $should_calc = true) {}

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currency_code_ = MethodsHelper::validateCurrencyCode(app("position_country_code")) ?? $this->currency?->short_name ?? "USD";
        return [
            "id" => $this->id,
            "amount" => $this->should_calc ? self::calcLocalPrice($currency_code_, $this->amount) : $this->amount,
            "impressions" => $this->impressions,
            "max_daily_amount" => $this->should_calc ? self::calcLocalPrice($currency_code_, $this->max_daily_amount) : $this->max_daily_amount,
            'currency' => [
                "name" => $currency_code_ ?? $this->currency->name,
                "short_name" => $currency_code_ ?? $this->currency->short_name,
            ],
            "country" => !empty($this->country) ? CountryResource::make($this->whenLoaded("country", $this->country)) : null,
            "status" => $this->status,
        ];
    }

    public static function calcLocalPrice($currency_code, $amount)
    {
        $rate = Currency::status()->where("short_name", $currency_code)->first()?->price_per_dollar;
        $data = $rate * $amount;
        return $data;
    }
}
