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
            'user' => UserResource::make($this->whenLoaded("user", $this->user)),
            "plan" => PlanResource::make($this->whenLoaded("plan", $this->plan)),
            "stripe_subscription_id" => $this->stripe_subscription_id,
            "stripe_client_secret" => $this->stripe_client_secret,
            "expires_at" => formatDate($this->expires_at),
            "status" => $this->status,
            "created_at" => formatDate($this->created_at),
            "updated_at" => formatDate($this->updated_at)
        ];
    }

    public static function customSubscription($model)
    {
        return [
            'id' => $model->id,
            "plan" => PlanResource::make($model->plan),
            "stripe_subscription_id" => $model->stripe_subscription_id,
            "stripe_client_secret" => $model->stripe_client_secret,
            "status" => $model->status,
            "created_at" => $model->created_at,
            "updated_at" => $model->updated_at
        ];
    }
}
