<?php

namespace App\Http\Resources\Finance\Plan;

use App\Constants\Account\User\UserConstants;
use App\Models\Plan;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    protected $country_plans;

    public function __construct($resource, $country_plans = null)
    {
        // Pass the resource to the parent constructor
        parent::__construct($resource);
        // Store the country_plan data
        $this->country_plans = $country_plans;
    }
    public function toArray($request)
    {
        // dd( $this->country_plan->lowered_cost);
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            "frequency" => $this->defaultDuration()?->frequency,
            'price' => $this->getPriceForPlan(), // Use the adjusted price here
            "discount" => $this->defaultDuration()?->discount,
            "status" => $this->status,
            "country" => $this->status,
            "is_active_subscription" => false,
            "currency" => $this->plan?->currency?->short_name ?? "USD",
            "durations" => PlanDurationResource::collection($this->whenLoaded("durations", $this->durations)),
            "benefits" => PlanBenefitResource::collection($this->whenLoaded("benefits", $this->benefits)),
            "created_at" => formatDate($this->created_at),
            "updated_at" => formatDate($this->updated_at)
        ];

        if (!empty($user = auth()->user())) {
            if ($user?->role == UserConstants::USER) {
                $active_sub = $user->activeSubscription;
                if (!empty($active_sub) && $active_sub->plan_id == $this->id) {
                    $data["is_active_subscription"] = true;
                } else if (empty($active_sub) && strtolower($this->name) == "freemium") {
                    $data["is_active_subscription"] = true;
                }
            }
        }

        return $data;
    }


    public function getPriceForPlan()
    {
        // Check if there are any country plans
        if ($this->country_plans && $this->country_plans->isNotEmpty()) {
            $countryPlan = $this->country_plans->first();
            return $countryPlan?->lowered_cost ?? $this->price; // Safely access lowered_cost and fallback to price
        }

        return $this->price; // If no country plans, use the default price
    }

    public function getCountry()
    {
        $country = $this->plan_service->getLocationCountryName();
        return $country ?? null; // Safely access lowered_cost and fallback to price
    }


    public function getflutterwaveId()

    { // Check if there are any country plans
        if ($this->country_plans) {
            $countryPlan = $this->country_plans->first();
            return $countryPlan->flutterwave_plan_id;
        }

        return $this->price; // If no country plans, use the default price
    }

    public static function custom(Plan $model)
    {
        return [
            'id' => $model->id,
            'name' => $model->name,
            'description' => $model->description,
            "frequency" => $model->defaultDuration()?->frequency,
            "price" => $model->defaultDuration()?->price,
            "discount" => $model->defaultDuration()?->discount,
            "status" => $model->status,
            "created_at" => formatDate($model->created_at),
            "updated_at" => formatDate($model->updated_at)
        ];
    }
}
