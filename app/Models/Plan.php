<?php

namespace App\Models;

use App\Constants\Finance\Plan\PlanConstants;
use App\Constants\General\StatusConstants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        "feature_cards" => "array"
    ];

    public function benefits()
    {
        return $this->hasMany(PlanBenefit::class, "plan_id");
    }

    public function durations()
    {
        return $this->hasMany(PlanDuration::class, "plan_id");
    }

    public function scopes()
    {
        return $this->hasMany(PlanScope::class, "plan_id");
    }

    public function defaultDuration()
    {
        $default_duration = $this->durations()->where("is_default", 1)->first();

        if (empty($default_duration)) {
            $default_duration = $this->durations()->first();
        }

        return $default_duration;
    }

    public function displayPrice()
    {
        $user = auth()->user();
        $default_duration = $this->defaultDuration();

        $plan_pricing_provider = PlanCountryPricingProvider::where([
            "plan_duration_id" => $default_duration?->id,
        ])->whereRelation("planCountryPricing", "country_id", $user?->pricing_country_id)
            ->first();

        if (!empty($plan_pricing_provider)) {
            $price = $this->plan_pricing_provider?->price;
        } else {
            $price = $default_duration?->price;
        }

        return $price;
    }

    public function scopeStatus($query, $status = StatusConstants::ACTIVE)
    {
        $query->where("status", $status);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function planCountryPricings()
    {
        return $this->hasMany(PlanCountryPricing::class);
    }

    public function formattedAmount()
    {
        return format_money($this->durations->price, 2, $this->plan->currency->symbol);
    }

    public function defaultDiscount()
    {
        $default_duration = $this->whereHas("durations")->orderBy("id", "asc")
            ->where('frequency', PlanConstants::YEARLY)
            ->first();

        if (empty($default_duration)) {
            $default_duration = $this->whereHas("durations")->orderBy("id", "asc")
                ->first();
        }
        return $default_duration->discount;
    }

    public function getFirstPlanDiscountForFrequency($frequency)
    {
        $default_duration = $this->defaultDuration();
        if ($default_duration) {
            $first_plan_duration = $this->durations()->where('frequency', $frequency)->first();

            if ($first_plan_duration) {
                return $first_plan_duration->discount;
            }
        }
        return null;
    }
}
