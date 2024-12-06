<?php

namespace App\Services\Finance\Plan;

use App\Constants\Finance\Payment\PaymentConstants;
use App\Constants\Finance\Plan\PlanConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\Currency;
use App\Models\Plan;
use App\Models\PlanCountryPricing;
use App\Models\PlanCountryPricingProvider;
use App\Models\PlanDuration;
use App\Services\Finance\PaymentGateways\Flutterwave\FlutterwaveService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlanCountryPricingService
{
    public static function getById($id): PlanCountryPricing
    {
        $plan = PlanCountryPricing::find($id);
        if (empty($plan)) {
            throw new ModelNotFoundException("Plan not found");
        }
        return $plan;
    }

    public static function validate(array $data, $id = null): array
    {
        $validator = Validator::make($data, [
            "type" => "required|string|" . Rule::in(array_keys(PlanConstants::COUNTRY_TYPE_OPTIONS)),
            "country_id" => 'required|exists:countries,id', // Corrected validation rule
            "plan_duration_id" => 'nullable|exists:plan_durations,id|' . Rule::requiredIf(($data["type"] ?? null) == PlanConstants::SINGLE_PLAN), // Corrected validation rule
            "lowered_cost" => "required|numeric",
            'status' => 'string|nullable',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public function create(array $data)
    {
        DB::beginTransaction();
        try {
            $data = self::validate($data);

            $plan_country_pricing = PlanCountryPricing::where([
                'country_id' => $data['country_id'],
                'type' => $data['type'],
            ])->exists();

            if ($plan_country_pricing) {
                throw new InvalidRequestException("A country-specific plan already exists. Update it instead.");
            }

            if ($data["type"] == PlanConstants::GENERAL) {
                $plan_country_pricing = $this->createGeneralPricing($data);
            }

            if ($data["type"] == PlanConstants::SINGLE_PLAN) {
                $plan_country_pricing = $this->createSinglePricing($data);
            }

            DB::commit();
            return $plan_country_pricing->refresh();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function createGeneralPricing(array $data)
    {
        $durations = PlanDuration::latest()->get();

        $plan_country_pricing = PlanCountryPricing::create([
            "type" => $data['type'],
            "country_id" => $data['country_id'],
            "lowered_cost" => $data['lowered_cost'],
            "status" => StatusConstants::ACTIVE,
        ]);

        $plan_country_pricing_records = [];

        foreach ($durations as $key => $duration) {
            $original_price = floatval((new PlanService)->parsePlanPrice($duration));

            $lowered_cost = $original_price - $data['lowered_cost'];

            if ($lowered_cost > 0) {
                $plan_country_pricing_records[] = PlanCountryPricingProvider::create([
                    "plan_country_pricing_id" => $plan_country_pricing->id,
                    "plan_id" => $duration->plan_id,
                    "plan_duration_id" => $duration->id,
                    "price" => $lowered_cost,
                    "discount" => 0,
                    "status" => StatusConstants::ACTIVE,
                ]);
            }
        }

        if (count($plan_country_pricing_records) > 0) {
            self::createFlutterwavePlan($plan_country_pricing_records);
        }

        return $plan_country_pricing->refresh();
    }

    public function createSinglePricing(array $data)
    {
        $durations = PlanDuration::where("id", $data["plan_duration_id"])->get();

        $plan_country_pricing = PlanCountryPricing::create([
            "type" => $data['type'],
            "country_id" => $data['country_id'],
            "lowered_cost" => $data['lowered_cost'],
            "status" => StatusConstants::ACTIVE,
        ]);

        $plan_country_pricing_records = [];

        foreach ($durations as $key => $duration) {
            $original_price = floatval((new PlanService)->parsePlanPrice($duration));

            $lowered_cost = $original_price - $data['lowered_cost'];

            if ($lowered_cost > 0) {
                $plan_country_pricing_records[] = PlanCountryPricingProvider::create([
                    "plan_country_pricing_id" => $plan_country_pricing->id,
                    "plan_id" => $duration->plan_id,
                    "plan_duration_id" => $duration->id,
                    "price" => $lowered_cost,
                    "discount" => 0,
                    "status" => StatusConstants::ACTIVE,
                ]);
            }
        }

        if (count($plan_country_pricing_records) > 0) {
            self::createFlutterwavePlan($plan_country_pricing_records);
        }

        return $plan_country_pricing->refresh();
    }

    public function update(array $data, $id)
    {
        DB::beginTransaction();
        try {
            $data = self::validate($data, $id);

            $plan_country_pricing = $this->getById($id);

            $plan_country_pricing_providers = $plan_country_pricing->pricingProviders;

            if ($data["lowered_cost"] != $plan_country_pricing->lowered_cost) {
                $this->cancelCountryFlutterwavePlan($plan_country_pricing);

                foreach ($plan_country_pricing_providers ?? [] as $plan_country_pricing_provider) {
                    $this->createFlutterwavePlan([$plan_country_pricing_provider]);
                }
            }

            $plan_country_pricing->update($data);
            DB::commit();
            return $plan_country_pricing;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function save(array $data, $id = null)
    {
        if (!empty($id)) {
            $plan = $this->update($data, $id);
        } else {
            $plan = $this->create($data);
        }

        return $plan;
    }

    public static function list()
    {
        $plan_country_pricings = PlanCountryPricing::status()->latest();
        return $plan_country_pricings;
    }

    public static function listByCurrentCountryPlan($plan_country_pricing_id)
    {
        $plan_country_pricing = PlanCountryPricing::status()
            ->orderByRaw("id = ? DESC", [$plan_country_pricing_id])
            ->latest()
            ->orderBy('id', 'DESC');

        return $plan_country_pricing;
    }

    public static function fetchCurrentPlan()
    {
        $user = auth()->user();
        $active_sub = $user->activeSubscription;
        if (!empty($active_sub)) {
            $plan_id = $active_sub->plan_id;
        } else {
            $free_plan = Plan::where("name", "LIKE", "%free%")->first();
            $plan_id = $free_plan?->id;
        }

        return $plan_id ?? null;
    }


    public static function createFlutterwavePlan($planCountryPricings)
    {
        foreach ($planCountryPricings as $plan_country_pricing) {
            $plan = $plan_country_pricing->plan;
            $duration = $plan_country_pricing->planDuration;

            $amount = $plan_country_pricing->price;
            $currency = Currency::where('short_name', 'USD')->first();

            $transaction_data = [
                "amount" => $amount,
                "name" => $plan->name,
                "interval" => strtolower($duration->frequency),
                "duration" => $duration->duration,
                "currency" => $currency ? $currency->short_name : 'USD',
            ];

            $response = (new FlutterwaveService)->setPlanData($transaction_data)->createPlan();

            if (!empty($response['data']['id'])) {
                $plan_country_pricing->update([
                    'provider' => PaymentConstants::FLUTTERWAVE,
                    'provider_plan_id' => $response["data"]['id'],
                ]);
            }
        }
    }

    public static function updateFlutterwavePlan($plan_country_pricing_provider)
    {
        $provider_plan_id = $plan_country_pricing_provider->provider_plan_id;

        if (!empty($provider_plan_id)) {
            $existingPlan = (new FlutterwaveService)->getPlan($provider_plan_id);

            if ($existingPlan && $existingPlan['data']['id'] == $provider_plan_id) {
                $transaction_data = [
                    "status" => strtolower($plan_country_pricing_provider->status),
                ];

                $response = (new FlutterwaveService)->setPlanData($transaction_data)
                    ->updatePlan($provider_plan_id);
            }
        }
    }

    public function deleteCountryPricing($plan_country_pricing)
    {
        $this->cancelCountryFlutterwavePlan($plan_country_pricing);
        $plan_country_pricing->delete();
    }

    public function deleteCountryPricingProvider($plan_country_pricing_provider)
    {
        $response = (new FlutterwaveService)
            ->cancelPlan($plan_country_pricing_provider->provider_plan_id);
            
        $plan_country_pricing_provider->delete();
    }


    public function cancelCountryFlutterwavePlan($plan_country_pricing)
    {
        $plan_country_pricing_providers = $plan_country_pricing->pricingProviders;
        foreach ($plan_country_pricing_providers as $plan_country_pricing_provider) {
            $response = (new FlutterwaveService)
                ->cancelPlan($plan_country_pricing_provider->provider_plan_id);

            if (isset($response)) {
                $plan_country_pricing_provider->update([
                    "status" => StatusConstants::INACTIVE
                ]);
            }
        }
    }
}
