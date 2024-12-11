<?php

namespace App\Http\Resources\Finance\Plan;

use App\Helpers\MethodsHelper;
use App\Models\Currency;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanDurationResource extends JsonResource
{
    public function toArray($request)
    {
        $display = $this->displayPrice();
        $currency_code_ = MethodsHelper::validateCurrencyCode(app("position_country_code")) ?? $this->plan?->currency?->short_name ?? "USD";
        $local_rate = self::calcLocalPrice($currency_code_, $display["price"]);

        return [
            'id' => $this->id,
            'frequency' => $this->frequency,
            'duration' => $this->duration,
            'price' =>  $local_rate ?? $display["price"],
            'discount' => $this->discount,
            'flutterwave_plan_id' => $display["flutterwave_plan_id"],
            'created_at' => formatDate($this->created_at),
            'updated_at' => formatDate($this->updated_at),
        ];
    }

    public static function calcLocalPrice($currency_code, $amount)
    {
        $rate = Currency::status()->where("short_name", $currency_code)->first()?->price_per_dollar;
        $data = $rate * $amount;
        return $data;
    }
}
