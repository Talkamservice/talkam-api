<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanDuration extends Model
{
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
        $userCountryName = $this->plan_service->getLocationCountryName(); // Replace with dynamic country if needed
        // Fetch the country-specific plan details
        $countryPlans = PlanCountryPricing::with(['plan.durations', 'country'])
            ->where('plan_id', $this->plan_id)
            ->whereHas('plan.durations', function ($query) {
                $query->where('is_default', 1); // Filter by the default duration, if applicable
            })
            ->whereHas('country', function ($query) use ($userCountryName) {
                $query->where('name', $userCountryName);
            })
            ->status()
            ->latest()
            ->get(); // Get all the records

        if ($countryPlans->isEmpty()) {
            return [
                'flutterwave_plan_id' => null,
                'lowered_cost' => null,
            ];
        }

        // Initialize the result array
        $details = [];

        // Loop through each country plan and assign the correct flutterwave_plan_id and lowered_cost
        foreach ($countryPlans as $countryPlan) {
            foreach ($countryPlan->plan->durations as $duration) {
                // Ensure frequency is normalized to lowercase
                $frequency = strtolower($duration->frequency);

                if ($frequency === 'monthly') {
                    $details['monthly'] = [
                        'flutterwave_plan_id' => $countryPlan->flutterwave_plan_id,
                        'lowered_cost' => $countryPlan->lowered_cost, // Assign price from PlanCountryPricing
                    ];
                } elseif ($frequency === 'yearly') {
                    $details['yearly'] = [
                        'flutterwave_plan_id' => $countryPlan->flutterwave_plan_id,
                        'lowered_cost' => $countryPlan->lowered_cost, // Assign price from PlanCountryPricing
                    ];
                }
            }
        }
        return $details;
    }
}
