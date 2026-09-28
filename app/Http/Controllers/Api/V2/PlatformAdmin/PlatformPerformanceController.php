<?php

namespace App\Http\Controllers\Api\V2\PlatformAdmin;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\PlatformAdmin\PlatformPerformanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Exception;

class PlatformPerformanceController extends Controller
{
    public function index(Request $request)
    {
        try {
            $overview = PlatformPerformanceService::overview(
                $request->boolean('flagged_only'),
                (int) $request->input('per_page', 10),
                max(1, (int) $request->input('page', 1))
            );
            return ApiHelper::validResponse("Performance overview returned successfully", $overview);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function updateThreshold(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "key" => "required|string",
                "value" => "required|numeric",
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $validated = $validator->validated();
            PlatformPerformanceService::updateThreshold($validated['key'], $validated['value']);
            return ApiHelper::validResponse("Threshold updated", PlatformPerformanceService::thresholds());
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function sendWarning(Request $request, $id)
    {
        try {
            PlatformPerformanceService::sendWarningEmail((int) $id, $request->input('note'));
            return ApiHelper::validResponse("Warning email sent", []);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function placeOnHold($id)
    {
        try {
            PlatformPerformanceService::placeOnReviewHold((int) $id);
            return ApiHelper::validResponse("Therapist placed on review hold", []);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function clearHold($id)
    {
        try {
            PlatformPerformanceService::clearReviewHold((int) $id);
            return ApiHelper::validResponse("Review hold cleared", []);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function forceReverification($id)
    {
        try {
            PlatformPerformanceService::forceReverification((int) $id);
            return ApiHelper::validResponse("Therapist flagged for re-verification", []);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function terminate(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), ["reason" => "required|string|max:500"]);
            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            PlatformPerformanceService::terminateAccount((int) $id, $validator->validated()['reason']);
            return ApiHelper::validResponse("Account terminated", []);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }
}
