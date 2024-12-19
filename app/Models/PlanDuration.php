<?php

namespace App\Models;

use App\Services\Finance\Plan\PlanService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanDuration extends Model
{

    protected $plan_service;

    public function __construct()
    {
        $this->plan_service = new PlanService;
    }

    use HasFactory;

    protected $guarded = [];

    public function plan()
    {
        return $this->belongsTo(Plan::class, "plan_id");
    }

    public function countryPlan()
    {
        return $this->hasMany(PlanCountryPricing::class, 'plan_duration_id');
    }


    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, "plan_duration_id");
    }

    public function formattedAmount()
    {
        return format_money($this->price - $this->discount, 2, $this->plan->currency->symbol);
    }

    public function originalPrice() {
        return ($this->discount / 100 * $this->price) + $this->price;
    }

    public function displayPrice()
    {
        $user = auth("sanctum")->user();

        $plan_pricing_provider = PlanCountryPricingProvider::where([
            "plan_duration_id" => $this->id,
        ])->whereRelation("planCountryPricing", "country_id", $user?->pricing_country_id)
            ->first();

        if (!empty($plan_pricing_provider)) {
            $response = [
                "price" => $plan_pricing_provider?->price,
                "flutterwave_plan_id" => $plan_pricing_provider->provider_plan_id
            ];
        } else {
            $response = [
                "price" => $this?->price,
                "flutterwave_plan_id" => $this->flutterwave_plan_id
            ];
        }

        return $response;
    }
}
