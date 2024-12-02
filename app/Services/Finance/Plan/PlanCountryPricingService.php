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
        // dd($data);
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

            // Ensure a plan doesn't already exist for this country
            $existingPlan = PlanCountryPricing::where('country_id', $data['country_id'])->exists();
            if ($existingPlan) {
                throw new Exception("A country-specific plan already exists. Update it instead.");
            }

            $plans = Plan::with('durations')->status()->get();
            if ($plans->isEmpty()) {
                throw new Exception("No active plans found to associate with country pricing.");
            }

            $planCountryPricingRecords = [];

            foreach ($plans as $plan) {
                foreach ($plan->durations as $duration) {
                    // Parse the original price
                    $originalPrice = floatval((new PlanService)->parsePlanPrice($duration));

                    // Calculate the reduced price and percentage reduction
                    $loweredCost = $originalPrice - $data['lowered_cost'];
                    if ($loweredCost < 0) {
                        throw new Exception("Lowered cost cannot exceed the original price.");
                    }

                    $percentageReduction = ($data['lowered_cost'] / $originalPrice) * 100;

                    $planCountryPricing = PlanCountryPricing::create([
                        "lowered_cost" => $loweredCost,
                        "percentage" => $percentageReduction,
                        "plan_id" => $plan->id,
                        "plan_duration_id" => $duration->id,
                        "country_id" => $data['country_id'],
                        "status" => StatusConstants::ACTIVE,
                    ]);

                    $planCountryPricingRecords[] = $planCountryPricing;
                }
            }

            // Create Flutterwave plans if required
            if (!empty($data["lowered_cost"])) {
                self::createFlutterwavePlan($planCountryPricingRecords);
            }

            DB::commit();
            return $planCountryPricingRecords;
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

            $planCountryPricingRecords = PlanCountryPricing::where('country_id', $data['country_id'])->get();
            if ($planCountryPricingRecords->isEmpty()) {
                throw new Exception("No existing pricing records found for this country.");
            }

            foreach ($planCountryPricingRecords as $planCountryPricing) {
                $plan = $planCountryPricing->plan;

                foreach ($plan->durations as $duration) {
                    // Parse the original price
                    $originalPrice = floatval((new PlanService)->parsePlanPrice($duration));

                    // Calculate the reduced price and percentage reduction
                    $loweredCost = $originalPrice - $data['lowered_cost'];
                    if ($loweredCost < 0) {
                        throw new Exception("Lowered cost cannot exceed the original price.");
                    }

                    $percentageReduction = ($data['lowered_cost'] / $originalPrice) * 100;

                    // Update the pricing record
                    $planCountryPricing->update([
                        "lowered_cost" => $loweredCost,
                        "percentage" => $percentageReduction,
                        'status' => $data['status'],
                    ]);
                }
            }

            // Update Flutterwave plans if necessary
            if (!empty($data["lowered_cost"])) {
                self::updateFlutterwavePlan($planCountryPricingRecords);
            }

            DB::commit();
            return $planCountryPricingRecords;
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


    public static function parseCountryPlanPrice($countryPricing, $durationIdentifier, $defaultPrice)
    {
        $plan = $countryPricing->plan; // Get the associated plan
        $duration = $plan->durations->firstWhere('duration', $durationIdentifier); // Find the specific duration

        if (!$duration) {
            return $defaultPrice; // If no matching duration, return default price
        }

        $loweredCost = $countryPricing->lowered_cost;

        if ($loweredCost && $loweredCost > 0) {
            return max(0, $duration->price - $loweredCost); // Apply lowered cost
        }

        return $duration->price; // Return the original price if no lowered cost
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

            foreach ($plan->durations as $duration) {
                $defaultPrice = floatval((new PlanService)->parsePlanPrice($duration));
                $durationIdentifier = $duration->duration; // Or any unique identifier for the duration
                $amount = floatval(self::parseCountryPlanPrice($plan_country_pricing, $durationIdentifier, $defaultPrice));
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
                        'flutterwave_plan_id' => $response["data"]['id'],
                        'flutterwave_plan_status' => $response["data"]['status'],
                    ]);
                }
            }
        }
    }

    public static function updateFlutterwavePlan($planCountryPricings)
    {
        foreach ($planCountryPricings as $plan_country_pricing) {
            $plan = $plan_country_pricing->plan;

            if (!empty($plan_country_pricing->flutterwave_plan_id)) {
                $existingPlan = (new FlutterwaveService)->getPlan($plan_country_pricing->flutterwave_plan_id);

                if ($existingPlan && $existingPlan['data']['id'] === $plan_country_pricing->flutterwave_plan_id) {
                    $transaction_data = [
                        "status" => $plan_country_pricing->status,
                    ];

                    if ($plan_country_pricing->status === 'inactive') {
                        $transaction_data['status'] = 'inactive';
                    }

                    $response = (new FlutterwaveService)->setPlanData($transaction_data)
                        ->updatePlan($plan_country_pricing->flutterwave_plan_id);

                    if (!empty($response)) {
                        $plan_country_pricing->update([
                            'flutterwave_plan_status' => $transaction_data['status'],
                        ]);
                    }
                } else {
                    self::createFlutterwavePlan([$plan_country_pricing]);
                }
            } else {
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
        $position = Location::get(); // Leave empty for the current user location.
        dd($position->countryName);
        return $position ? $position->countryName : null;
        $response = (new FlutterwaveService)->getPlans();
        return $response;
    }
}
