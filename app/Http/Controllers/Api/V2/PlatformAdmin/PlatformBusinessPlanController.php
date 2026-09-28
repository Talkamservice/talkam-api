<?php

namespace App\Http\Controllers\Api\V2\PlatformAdmin;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\PlatformAdmin\PlatformBusinessPlanService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class PlatformBusinessPlanController extends Controller
{
    public function index()
    {
        try {
            return ApiHelper::validResponse("Plans returned successfully", PlatformBusinessPlanService::list());
        } catch (Exception $e) {
            return $this->failure($e);
        }
    }

    public function store(Request $request)
    {
        try {
            $plan = PlatformBusinessPlanService::create($request->all());

            return ApiHelper::validResponse("Plan created successfully", $plan);
        } catch (Exception $e) {
            return $this->failure($e);
        }
    }

    public function update(Request $request, $plan)
    {
        try {
            $row = PlatformBusinessPlanService::update((int) $plan, $request->all());

            return ApiHelper::validResponse("Plan updated successfully", $row);
        } catch (Exception $e) {
            return $this->failure($e);
        }
    }

    public function destroy($plan)
    {
        try {
            PlatformBusinessPlanService::delete((int) $plan);

            return ApiHelper::validResponse("Plan deleted successfully");
        } catch (Exception $e) {
            return $this->failure($e);
        }
    }

    private function failure(Exception $e)
    {
        if ($e instanceof ValidationException) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        }

        if ($e instanceof ModelNotFoundException) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::NOT_FOUND_ERR_CODE, null, null);
        }

        return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
    }
}
