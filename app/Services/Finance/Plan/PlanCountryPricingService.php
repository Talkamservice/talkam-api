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
use Stevebauman\Location\Facades\Location;
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
            "lowered_cost" => "required|numeric",
            'status' => 'string|nullable',
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
            // Check if a plan already exists for the given country
            $existingPlan = PlanCountryPricing::where('country_id', $data['country_id'])->exists();
            if ($existingPlan) {
                throw new Exception("Sorry, you cannot create a plan for this country again as one already exists. Please visit the country plan page to update the existing plan.");
            }
            $plans = Plan::with('durations')->status()->get();
            if ($plans->isEmpty()) {
                throw new Exception("No plans found to associate with country plan pricing.");
            }

            $plan_country_pricing_records = [];
            foreach ($plans as $plan) {
                foreach ($plan->durations as $duration) {
                    $percentage =  ($data['lowered_cost'] / 100)  * floatval((new PlanService)->parsePlanPrice($duration));
                    $plan_country_pricing = PlanCountryPricing::create([
                        "lowered_cost" => $data['lowered_cost'],
                        "percentage" => $percentage,
                        "plan_id" => $plan->id,
                        "country_id" => $data['country_id'],
                        "status" => StatusConstants::ACTIVE,
                    ]);

                    $plan_country_pricing_records[] = $plan_country_pricing;
                }
            }

            if (!empty($data["lowered_cost"])) {
                self::createFlutterwavePlan($plan_country_pricing_records);
            }

            DB::commit();
            return $plan_country_pricing_records;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }


    public static function update(array $data, $id)
    {
        DB::beginTransaction();
        try {
            // Validate the data
            $data = self::validate($data, $id);

            // Retrieve the existing PlanCountryPricing records
            $plan_country_pricing_records = PlanCountryPricing::where('country_id', $data['country_id'])->get();

            if ($plan_country_pricing_records->isEmpty()) {
                throw new Exception("No existing pricing records found for this country.");
            }

            // Iterate through each pricing record and update
            foreach ($plan_country_pricing_records as $plan_country_pricing) {
                $plan = $plan_country_pricing->plan;

                foreach ($plan->durations as $duration) {
                    $percentage = ($data['lowered_cost'] / 100) * floatval((new PlanService)->parsePlanPrice($duration));

                    // Update the pricing record
                    $plan_country_pricing->update([
                        "lowered_cost" => $data['lowered_cost'],
                        "percentage" => $percentage,
                        'status' => $data['status'],
                    ]);
                }
            }

            // Update Flutterwave plans if a lowered_cost is present
            if (!empty($data["lowered_cost"])) {
                $existing_country_plan = $plan_country_pricing->flutterwave_plan_id;
                self::updateFlutterwavePlan($plan_country_pricing_records, $existing_country_plan);
            }

            DB::commit();
            return $plan_country_pricing_records;
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
        if (!empty($countryPricing->lowered_cost) && is_numeric($countryPricing->lowered_cost)) {
            return max(0, $defaultPrice - $countryPricing->lowered_cost);
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
                $defaultPrice = floatval((new PlanService)->parsePlanPrice($duration));
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
                    if (!empty($response['data']['id'])) {
                        $plan_country_pricing->update([
                            'flutterwave_plan_id' => $response["data"]['id']
                        ]);
                    }
                }
            }
        }
    }




    public static function updateFlutterwavePlan($planCountryPricings)
    {
        foreach ($planCountryPricings as $plan_country_pricing) {
            $plan = $plan_country_pricing->plan; // Retrieve the related plan

            // Check if the Flutterwave plan ID exists
            if (!empty($plan_country_pricing->flutterwave_plan_id)) {
                // Fetch plan details from Flutterwave
                $existingPlan = (new FlutterwaveService)->getPlan($plan_country_pricing->flutterwave_plan_id);

                if ($existingPlan && $existingPlan['data']['id'] == $plan_country_pricing->flutterwave_plan_id) {
                    // Check the status and update the Flutterwave plan accordingly
                    if ($plan_country_pricing->status === 'inactive') {
                        // Set the Flutterwave plan to inactive
                        $response = (new FlutterwaveService)->setPlanData(['status' => 'inactive'])
                            ->updatePlan($plan_country_pricing->flutterwave_plan_id);

                        if (!empty($response)) {
                            $plan_country_pricing->update([
                                'flutterwave_plan_status' => 'inactive',
                            ]);
                        }
                    } else {
                        // Update the Flutterwave plan with other details if status is active
                        $transaction_data = [
                            "status" => $plan_country_pricing->status,
                        ];
                        $response = (new FlutterwaveService)->setPlanData($transaction_data)
                            ->updatePlan($plan_country_pricing->flutterwave_plan_id);

                        if (!empty($response)) {
                            $plan_country_pricing->update([
                                'flutterwave_plan_status' => 'active',
                            ]);
                        }
                    }
                }
            } else {
                // Handle cases where Flutterwave plan ID is missing
                self::createFlutterwavePlan([$plan_country_pricing]);
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
