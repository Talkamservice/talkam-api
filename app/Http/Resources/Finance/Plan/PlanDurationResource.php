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
         $frequency = strtolower($this->frequency); // Ensure frequency is lowercase (e.g., 'monthly', 'yearly')
     
         return [
             'id' => $this->id,
             'frequency' => $this->frequency,
             'duration' => $this->duration,
             'price' => isset($countryPlan[$frequency]) ? $countryPlan[$frequency]['lowered_cost'] : $this->price, // Check if frequency exists in $countryPlan
             'discount' => $this->discount,
             'flutterwave_plan_id' => isset($countryPlan[$frequency]) ? $countryPlan[$frequency]['flutterwave_plan_id'] : $this->flutterwave_plan_id, // Safely check for flutterwave_plan_id
             'created_at' => formatDate($this->created_at),
             'updated_at' => formatDate($this->updated_at),
         ];
     }
     
}
