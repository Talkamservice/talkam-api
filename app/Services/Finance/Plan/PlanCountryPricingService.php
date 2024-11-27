<?php

namespace App\Services\Finance\Plan;

use App\Constants\General\StatusConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Plan;
use App\Models\PlanCountryPricing;
use App\Services\Finance\PaymentGateways\Flutterwave\FlutterwaveService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            "country_id" => 'required|exists:countries,id', // Corrected validation rule
            "discount" => "required|numeric",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public static function create(array $data)
    {
        DB::beginTransaction();
        try {
            $data = self::validate($data);

            $plans = Plan::with('durations')->status()->get();
            if ($plans->isEmpty()) {
                throw new Exception("No plans found to associate with country plan pricing.");
            }

            $plan_country_pricing_records = [];
            foreach ($plans as $plan) {
                foreach ($plan->durations as $duration) {
                    $lowered_cost =  ($data['discount'] / 100)  * floatval((new PlanService)->parsePlanPrice($duration));

                    $plan_country_pricing = PlanCountryPricing::create([
                        "discount" => $data['discount'],
                        "lowered_cost" => $lowered_cost,
                        "plan_id" => $plan->id,
                        "country_id" => $data['country_id'],
                        "status" => StatusConstants::ACTIVE,
                    ]);

                    $plan_country_pricing_records[] = $plan_country_pricing;
                }
            }

            if (!empty($data["discount"])) {
                self::createFlutterwavePlan($plan_country_pricing_records);
            }

            DB::commit();
            return $plan_country_pricing_records;
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Error creating country pricing: ', ['exception' => $th]);
            throw $th;
        }
    }


    public static function update(array $data, $id)
    {
        DB::beginTransaction();
        try {
            $data = self::validate($data, $id);
            $plan_country_pricing = self::getById($id);

            $plan_country_pricing->update([
                "price" => $data["price"]
            ]);

            $plan_country_pricing->plan->scopes()->delete();
            foreach ($data["scopes"] as $key => $value) {
                (new PlanScopeService)->save([
                    "plan_id" => $plan_country_pricing->plan->id,
                    "title" => $key,
                    "value" =>  $value,
                    "status" => StatusConstants::ACTIVE
                ]);
            }
            // Fetch the existing durations before deletion
            if (!empty($data["price"] ?? null) && !empty($plan_country_pricing->plan->frequency ?? null)) {
                $existingDurations = $plan_country_pricing->plan->durations()->pluck('flutterwave_plan_id', 'id')->toArray();
                $plan_country_pricing->plan->durations()->delete();
                (new PlanDurationService)->saveMultiple($data, $plan_country_pricing, $existingDurations);
                self::updateFlutterwavePlan($plan_country_pricing, $existingDurations);
            }
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


    private static function parseCountryPlanPrice($countryPricing, $defaultPrice)
    {
        // Ensure default price is valid
        if ($defaultPrice <= 0) {
            return 0; // Return 0 for invalid or free plans
        }
        // If `discount` is provided, calculate the new price
        if (!empty($countryPricing->discount) && is_numeric($countryPricing->discount)) {
            return max(0, $defaultPrice - $countryPricing->discount);
        }
        // If `lowered_cost` is provided as a percentage, calculate the new price
        if (!empty($countryPricing->lowered_cost) && is_numeric($countryPricing->lowered_cost)) {
            $discount = ($countryPricing->lowered_cost / 100) * $defaultPrice;
            return max(0, $defaultPrice - $discount);
        }

        // If no discount is applied, return the default price
        return $defaultPrice;
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
            $plan = $plan_country_pricing->plan; // Retrieve related plan

            foreach ($plan->durations as $duration) {
                $defaultPrice = $duration->price;
                $amount = floatval(self::parseCountryPlanPrice($plan_country_pricing, $defaultPrice));  // Pass plan_country_pricing to parse

                $currency = Currency::where('short_name', 'USD')->first();

                $transaction_data = [
                    "amount" => $amount,
                    "name" => $plan_country_pricing->plan->name,
                    "interval" => strtolower($duration->frequency),
                    "duration" => $duration->duration,
                    "currency" => $currency ? $currency->short_name : 'USD',
                ];

                // Create the plan on Flutterwave
                $response = (new FlutterwaveService)->setPlanData($transaction_data)
                    ->createPlan();

                if (!empty($response)) {
                    $duration->update([
                        'flutterwave_plan_id' => $response["data"]['id']
                    ]);
                }
            }
        }
    }




    public static function updateFlutterwavePlan($plan, $existingDurations)
    {
        foreach ($existingDurations as $duration) {
            // Check if the duration has a flutterwave_plan_id
            if (!empty($duration->flutterwave_plan_id)) {
                // Fetch plan details from Flutterwave using flutterwave_plan_id
                $existingPlan = (new FlutterwaveService)->getPlan($duration->flutterwave_plan_id);

                if ($existingPlan && $existingPlan['data']['id'] == $duration->flutterwave_plan_id) {
                    // If the plan exists and fields like amount or interval have changed
                    if ($plan->isDirty(['amount', 'interval', 'duration'])) {
                        // Create a new Flutterwave plan and cancel the old one
                        self::createFlutterwavePlan($plan);
                        self::cancelFlutterwavePlan($duration);
                    } else {
                        // Otherwise, update allowed fields
                        $transaction_data = [
                            "name" => $plan->name,
                            "status" => $plan->status,
                        ];
                        $response = (new FlutterwaveService)->setPlanData($transaction_data)
                            ->updatePlan($duration->flutterwave_plan_id);
                        if (!empty($response)) {
                            // Update flutterwave_plan_id if necessary
                            $duration->update([
                                'flutterwave_plan_id' => $response["data"]['id']
                            ]);
                        }
                        return $response;
                    }
                }
            } else {
                // Handle cases where flutterwave_plan_id is missing
                self::createFlutterwavePlan($plan);
            }
        }
    }



    public static function cancelFlutterwavePlan($plan)
    {
        $durations = $plan->durations;
        foreach ($durations as $duration) {
            $response = (new FlutterwaveService)->cancelPlan($duration->flutterwave_plan_id);
            return $response;
        }
    }

    public static function cancelCountryFlutterwavePlan($planCountryPricings)
    {
        foreach ($planCountryPricings as $plan_country_pricing) {
            $plan = $plan_country_pricing->plan; // Retrieve related plan

            $durations = $plan->durations;
            foreach ($durations as $duration) {
                $response = (new FlutterwaveService)->cancelPlan($duration->flutterwave_plan_id);
                return $response;
            }
        }
    }

    public static function getFlutterwavePlan($plan)
    {
        $durations = $plan->durations;
        foreach ($durations as $duration) {
            $response = (new FlutterwaveService)
                ->getPlan($duration->flutterwave_plan_id);

            return $response;
        }
    }

    public static function getFlutterwavePlans()
    {
        $response = (new FlutterwaveService)->getPlans();
        return $response;
    }
}
