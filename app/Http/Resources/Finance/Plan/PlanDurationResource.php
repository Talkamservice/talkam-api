<?php

namespace App\Http\Resources\Finance\Plan;

use Illuminate\Http\Resources\Json\JsonResource;

class PlanDurationResource extends JsonResource
{
    public function toArray($request)
    {
        $display = $this->displayPrice();
        return [
            'id' => $this->id,
            'frequency' => $this->frequency,
            'duration' => $this->duration,
            'price' => $display["price"],
            'discount' => $this->discount,
            'flutterwave_plan_id' => $display["flutterwave_plan_id"],
            'created_at' => formatDate($this->created_at),
            'updated_at' => formatDate($this->updated_at),
        ];
    }
}
