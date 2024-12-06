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

    public function getCountryPlanDetails()
    {
        // $userCountryName = $this->plan_service->getLocationCountryName(); 
        $userCountryName = 'Nigeria';

        $countryPlans = PlanCountryPricing::whereHas('country', function ($query) use ($userCountryName) {
            $query->where('name', $userCountryName);
        })
            ->status()
            ->get();
        if (!$countryPlans) {
            return [
                'flutterwave_plan_id' => null,
                'lowered_cost' => null,
            ];
        }
        return $countryPlans->map(function ($countryPlan) {
            return [
                'flutterwave_plan_id' => $countryPlan->flutterwave_plan_id,
                'lowered_cost' => $countryPlan->lowered_cost,
            ];
        });
    }
}
