<?php

namespace App\Http\Resources\Finance\Plan;

use App\Models\PlanDuration;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanDurationResource extends JsonResource
{
    protected $plan_duration_model;

    public function __construct($resource)
    {
        parent::__construct($resource); // Call the parent constructor to set the resource
        $this->plan_duration_model = new PlanDuration();
    }
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */

    public function toArray($request)
    {
        $countryPlanDetails = $this->getCountryPlanDetails();
        return [
            'id' => $this->id,
            'frequency' => $this->frequency,
            'duration' => $this->duration,
            'price' => $countryPlanDetails['lowered_cost'] ?? $this->price,
            'discount' => $this->discount,
            'flutterwave_plan_id' => $countryPlanDetails['flutterwave_plan_id'] ?? $this->flutterwave_plan_id,
            'created_at' => formatDate($this->created_at),
            'updated_at' => formatDate($this->updated_at),
        ];
    }
}
