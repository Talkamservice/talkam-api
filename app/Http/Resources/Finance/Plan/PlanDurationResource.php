<?php

namespace App\Http\Resources\Finance\Plan;

use Illuminate\Http\Resources\Json\JsonResource;

class PlanDurationResource extends JsonResource
{
    /**
     * Initialize the resource with country plans.
     *
     * @param mixed $resource
     * @param mixed $countryPlanDetails
     */

    public function toArray($request)
    {
        $countryPlan = $this->getCountryPlanDetails();
        return [
            'id' => $this->id,
            'frequency' => $this->frequency,
            'duration' => $this->duration,
            'price' => $countryPlan['lowered_cost'] ?? $this->price, // Use lowered_cost if available
            'discount' => $this->discount,
            'flutterwave_plan_id' => $countryPlan['flutterwave_plan_id'] ?? $this->flutterwave_plan_id, // Use country-specific ID if available
            'created_at' => formatDate($this->created_at),
            'updated_at' => formatDate($this->updated_at),
        ];
    }


}
