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
        // Define the user's country name (or make this dynamic)
        $userCountryName = $this->plan_service->getLocationCountryName(); 
             // Fetch the country-specific plan details for the current plan_duration
        $countryPlans = $this->countryPlan()
            ->whereHas('country', function ($query) use ($userCountryName) {
                $query->where('name', $userCountryName);
            })
            ->where('plan_id', $this->plan_id) // Ensure the correct plan is matched
            ->status()
            ->get(); // Get all the records
          
        if ($countryPlans->isEmpty()) {
            return [
                'flutterwave_plan_id' => null,
                'lowered_cost' => null,
            ];
        }

        // Find the matching country plan, for example, by matching the plan duration
        $selectedPlan = $countryPlans->first();

        return [
            'flutterwave_plan_id' => $selectedPlan->flutterwave_plan_id,
            'lowered_cost' => $selectedPlan->lowered_cost,
        ];
    }
}
