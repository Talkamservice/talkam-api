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
            // Validate incoming data
            $data = self::validate($data);
            $plan_country_pricing = null;
            $plans = Plan::with('durations')->status()->get();
            if ($plans->isEmpty()) {
                throw new Exception("No plans found to associate with country plan pricing.");
            }
            foreach ($plans as $key => $plan) {
                foreach ($plan->durations as $duration) {
                    $lowered_cost = ($data['discount'] / $duration->price) * 100;

                    // Create PlanCountryPricing record
                    $plan_country_pricing = PlanCountryPricing::create([
                        "discount" => $data['discount'],
                        "lowered_cost" => $lowered_cost,
                        "plan_id" => $plan->id,
                        "country_id" => $data['country_id'],
                        "status" => StatusConstants::ACTIVE,
                    ]);
                    Log::info('PlanCountryPricing created: ', $plan_country_pricing->toArray());
                    // If necessary, create Flutterwave plan
                    if (!empty($data["discount"])) {
                        self::createFlutterwavePlan($plan_country_pricing);
                    }
                }
            }

            DB::commit();
            return $plan_country_pricing;
        } catch (\Throwable $th) {
            DB::rollBack();
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
        // Check if a fixed discount is provided
        if (!empty($countryPricing->fixed_discount)) {
            return max(0, $defaultPrice - $countryPricing->fixed_discount);
        }

        // Calculate the price using a percentage discount
        if (!empty($countryPricing->percentage_discount) && $countryPricing->percentage_discount > 0) {
            $discount = ($countryPricing->percentage_discount / 100) * $defaultPrice;
            return $defaultPrice - $discount;
        }

        // If no country-specific price or discount, return the default price
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


    public static function createFlutterwavePlan($plan_country_pricing)
    {
        $durations = $plan_country_pricing->plan->durations; // Access durations via Plan model

        foreach ($durations as $duration) {
            // Calculate the final price using the country pricing
            $defaultPrice = $duration->price; // Use the default price from the duration
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
