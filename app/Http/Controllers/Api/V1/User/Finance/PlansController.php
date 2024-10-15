<?php

namespace App\Http\Controllers\Api\V1\Finance\User;

use App\Constants\Account\User\UserConstants;
use App\Constants\General\ApiConstants;
use App\Exceptions\Finance\PlanException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Finance\Plan\PlanResource;
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
            $builder = $this->plan_service;
            $plan_id = $this->plan_service->fetchCurrentPlan();
    
            if (!empty($plan_id)) {
                $builder = $builder->listByCurrentPlan($plan_id);
            } else {
                $builder = $builder->list();
            }

            $plans = $builder->get();
            $data = PlanResource::collection($plans);
            return ApiHelper::validResponse("Plans returned successfully", $data);
        } catch (Exception $e) {
            //throw $th;
            return ApiHelper::problemResponse("Something went wrong while trying to process your request", ApiConstants::SERVER_ERR_CODE,  $request, $e);
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
