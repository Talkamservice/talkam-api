<?php

namespace App\Http\Resources\Finance\Subscription;

use App\Http\Resources\Finance\Plan\PlanResource;
use App\Http\Resources\Users\UserResource;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user' => !empty($this->user) ? UserResource::custom($this->user) : null,
            "plan" => !empty($this->plan) ? PlanResource::custom($this->plan) : null,
            "flutterwave_subscription_id" => $this->flutterwave_subscription_id,
            "expires_at" => formatDate($this->expires_at),
            "renewal_cancelled_at" => formatDate($this->renewal_cancelled_at),
            "status" => $this->status,
            "created_at" => formatDate($this->created_at),
            "updated_at" => formatDate($this->updated_at)
        ];
    }

    public static function custom($model)
    {
        return [
            'id' => $model->id,
            "plan" => PlanResource::make($model->plan),
            "flutterwave_subscription_id" => $model->flutterwave_subscription_id,
            "status" => $model->status,
            "renewal_cancelled_at" => formatDate($model->renewal_cancelled_at),
            "created_at" => $model->created_at,
            "updated_at" => $model->updated_at
        ];
    }
}
