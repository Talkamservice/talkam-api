<?php

namespace App\Http\Resources\Finance\Plan;

use Illuminate\Http\Resources\Json\JsonResource;

class PlanDurationResource extends JsonResource
{
    protected $country_plans;

    /**
     * Initialize the resource with country plans.
     *
     * @param mixed $resource
     * @param mixed $country_plans
     */
    public function __construct($resource, $country_plans = null)
    {
        parent::__construct($resource);
        $this->country_plans = $country_plans;
    }

    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'frequency' => $this->frequency,
            'duration' => $this->duration,
            'price' => 50 ?? $this->price,
            'discount' => $this->discount,
            'flutterwave_plan_id' => $this->flutterwave_plan_id,
            'created_at' => formatDate($this->created_at),
            'updated_at' => formatDate($this->updated_at),
        ];
    }

    /**
     * Get the flutterwave_plan_id from the country plans.
     */
    // public function getFlutterwaveId()
    // {
    //     if ($this->country_plans) {
    //         $countryPlan = $this->country_plans->first();
    //         return $countryPlan->flutterwave_plan_id ?? $this->flutterwave_plan_id;
    //     }

    //     return $this->flutterwave_plan_id; // Default value if not found
    // }

    // /**
    //  * Get the price from country plans or fallback to the default price.
    //  */
    // public function getPriceForPlan()
    // {
    //     if ($this->country_plans) {
    //         $countryPlan = $this->country_plans->first();
    //         return $countryPlan->lowered_cost ?? $this->price;
    //     }

    //     return $this->price;
    // }
}
