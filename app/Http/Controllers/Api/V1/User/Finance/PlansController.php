<?php

namespace App\Http\Controllers\Api\V1\User\Finance;

use App\Constants\Account\User\UserConstants;
use App\Constants\General\ApiConstants;
use App\Exceptions\Finance\PlanException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Finance\Plan\PlanDurationResource;
use App\Http\Resources\Finance\Plan\PlanResource;
use App\Models\PlanCountryPricing;
use App\Services\Finance\Plan\PlanService;
use Exception;
use Illuminate\Http\Request;

class PlansController extends Controller
{
    protected $plan_service;

    public function __construct()
    {
        $this->plan_service = new PlanService;
    }

    public function index(Request $request)
    {
        try {
            // Get the plan service builder
            $builder = $this->plan_service;

            // Fetch the current plan id
            $plan_id = $this->plan_service->fetchCurrentPlan();

            // Modify the builder based on the current plan id
            if (!empty($plan_id)) {
                $builder = $builder->listByCurrentPlan($plan_id);
            } else {
                $builder = $builder->list();
            }

            // Fetch the plans using the builder
            $plans = $builder->get(); // Ensure you execute the query to get results as a collection

            // Fetch country-specific plans
            $userCountryName = $this->plan_service->getLocationCountryName(); // Replace with dynamic country if needed
            $countryPlans = PlanCountryPricing::with('plan')
                ->whereHas('country', function ($query) use ($userCountryName) {
                    $query->where('name', $userCountryName);
                })
                ->status()
                ->latest()
                ->get()
                ->keyBy('plan_id'); // Key by plan_id to easily access country-specific plans

            // Fetch all plans (including relationships like durations)
            $plans = $builder->get();

            $plans->each(function ($plan) use ($countryPlans) {
                $plan->country_plans = $countryPlans->where('plan_id', $plan->id);
            });

            // Map the plans and include durations and other necessary relationships
            $data = $plans->map(function ($plan) {
                $durations = $plan->durations->map(function ($duration) use ($plan) {
                    $country_plans = $plan->country_plans;
                    return new PlanDurationResource($duration, $country_plans);
                });

                return new PlanResource($plan, $plan->country_plans);
            });


            // Return the response
            return ApiHelper::validResponse("Plans returned successfully", $data);
        } catch (Exception $e) {
            // Handle exceptions
            return ApiHelper::problemResponse(
                "Something went wrong while trying to process your request",
                ApiConstants::SERVER_ERR_CODE,
                $request,
                $e
            );
        }
    }



    public function show(Request $request, $plan_id)
    {
        try {
            $plan = $this->plan_service->getById($plan_id);
            $data = PlanResource::make($plan);
            return ApiHelper::validResponse("Plans returned successfully", $data);
        } catch (ModelNotFoundException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE,  $request, $e);
        } catch (Exception $e) {
            //throw $th;
            return ApiHelper::problemResponse("Something went wrong while trying to process your request", ApiConstants::SERVER_ERR_CODE,  $request, $e);
        }
    }
}
