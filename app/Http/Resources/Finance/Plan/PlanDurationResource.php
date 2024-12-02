<?php

namespace App\Http\Resources\Finance\Plan;

use Illuminate\Http\Resources\Json\JsonResource;

class PlanDurationResource extends JsonResource
{

    public function toArray($request)
    {
        // Get the country plan details
        $countryPlan = $this->getCountryPlanDetails();
        // Use the country plan details if available, otherwise fallback to defaults
        $price = isset($countryPlan['lowered_cost']) ? $countryPlan['lowered_cost'] : $this->price;
        $flutterwavePlanId = isset($countryPlan['flutterwave_plan_id']) ? $countryPlan['flutterwave_plan_id'] : $this->flutterwave_plan_id;

        return [
            'id' => $this->id,
            'frequency' => $this->frequency,
            'duration' => $this->duration,
            'price' => $price, // Use the price from the country plan or fallback to the default price
            'discount' => $this->discount,
            'flutterwave_plan_id' => $flutterwavePlanId, // Use the flutterwave plan ID from the country plan or fallback to the default ID
            'created_at' => formatDate($this->created_at),
            'updated_at' => formatDate($this->updated_at),
        ];
    }
}
