<?php

namespace App\Http\Resources\Finance\Plan;

use App\Constants\Account\User\UserConstants;
use App\Helpers\MethodsHelper;
use App\Models\Currency;
use App\Models\Plan;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */

    public function toArray($request)
    {
        $user = auth("sanctum")->user();
        $position_country_code = app("position_country_code");
        $currency_code_ = MethodsHelper::validateCurrencyCode($position_country_code) ?? $this->currency?->short_name ?? "USD";
        
        $display_price = $this->displayPrice();
        $local_rate = self::calcLocalPrice($currency_code_, $display_price);
        
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            "frequency" => $this->defaultDuration()?->frequency,
            'price' => $local_rate ?? $this->displayPrice(),
            "discount" => $this->defaultDuration()?->discount,
            "status" => $this->status,
            "country" => $user?->country?->name,
            "is_active_subscription" => false,
            "currency" => $currency_code_,
            "durations" => PlanDurationResource::collection($this->whenLoaded("durations", $this->durations)),
            "benefits" => PlanBenefitResource::collection($this->whenLoaded("benefits", $this->benefits)),
            "created_at" => formatDate($this->created_at),
            "updated_at" => formatDate($this->updated_at)
        ];

        if (!empty($user)) {
            if ($user?->role == UserConstants::USER) {
                $active_sub = $user->activeSubscription;
                if (!empty($active_sub) && $active_sub->plan_id == $this->id) {
                    $data["is_active_subscription"] = true;
                } else if (empty($active_sub) && strtolower($this->name) == "freemium") {
                    $data["is_active_subscription"] = true;
                }
            }
        }

        return $data;
    }

    public static function custom(Plan $model)
    {
        $position_country_code = app("position_country_code");
        $currency_code_ = MethodsHelper::validateCurrencyCode($position_country_code) ?? $model->currency?->short_name ?? "USD";
        
        $display_price = $model->displayPrice();
        $local_rate = self::calcLocalPrice($currency_code_, $display_price);

        return [
            'id' => $model->id,
            'name' => $model->name,
            'description' => $model->description,
            "frequency" => $model->defaultDuration()?->frequency,
            "currency" => $currency_code_,
            'price' => $local_rate ?? $model->displayPrice(),
            "discount" => $model->defaultDuration()?->discount,
            "status" => $model->status,
            "created_at" => formatDate($model->created_at),
            "updated_at" => formatDate($model->updated_at)
        ];
    }

    public static function calcLocalPrice($currency_code, $amount)
    {
        $rate = Currency::status()->where("short_name", $currency_code)->first()?->price_per_dollar;
        $data = $rate * $amount;
        return $data;
    }
}
