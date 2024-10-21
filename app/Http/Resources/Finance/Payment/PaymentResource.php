<?php

namespace App\Http\Resources\Finance\Payment;

use App\Http\Resources\Users\UserResource;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
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
            "currency" => $this->currency,
            "amount" => $this->amount,
            "fees" => $this->fees,
            "reference" => $this->reference,
            "activity" => $this->activity,
            "description" => $this->description,
            "metadata" => $this->metadata,
            "status" => $this->status,
            "created_at" => formatDate($this->created_at),
            "updated_at" => formatDate($this->updated_at)
        ];
    }
}
