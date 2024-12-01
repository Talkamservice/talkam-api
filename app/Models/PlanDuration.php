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

        // Fetch the country-specific plan details
        $countryPlan = PlanCountryPricing::with('plan')
            ->where('plan_id', $this->plan_id) // Filter by this duration's plan ID
            ->whereHas('country', function ($query) use ($userCountryName) {
                $query->where('name', $userCountryName);
            })->status()->latest()->first(); // Fetch only the latest record (if needed)

        // If no country-specific plan exists, return default values
        if (!$countryPlan) {
            return [
                'flutterwave_plan_id' => null,
                'lowered_cost' => null,
            ];
        }
        // Return the details
        return [
            'flutterwave_plan_id' => $countryPlan->flutterwave_plan_id,
            'lowered_cost' => $countryPlan->lowered_cost,
        ];
    }
}
