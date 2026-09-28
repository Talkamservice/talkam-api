<?php

namespace App\Http\Controllers\Api\V2\Therapist;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\Therapist\TherapistClientService;
use App\Services\Therapist\TreatmentPlanService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class ClientController extends Controller
{
    public $treatment_plan_service;
    function __construct()
    {
        $this->treatment_plan_service = new TreatmentPlanService;
    }

    private function forbiddenUnlessTherapist()
    {
        if (empty(auth()->user()->therapist)) {
            return ApiHelper::problemResponse("Forbidden", ApiConstants::FORBIDDEN_ERR_CODE, null, null);
        }

        return null;
    }

    public function index()
    {
        if ($forbidden = $this->forbiddenUnlessTherapist()) {
            return $forbidden;
        }

        try {
            $data = TherapistClientService::roster(auth()->user()->therapist);
            return ApiHelper::validResponse("Clients returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function show($user)
    {
        if ($forbidden = $this->forbiddenUnlessTherapist()) {
            return $forbidden;
        }

        try {
            $data = TherapistClientService::details(auth()->user()->therapist, $user);
            return ApiHelper::validResponse("Client details returned successfully", $data);
        } catch (ModelNotFoundException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::NOT_FOUND_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function setTreatmentPlan(Request $request, $user)
    {
        if ($forbidden = $this->forbiddenUnlessTherapist()) {
            return $forbidden;
        }

        try {
            $plan = $this->treatment_plan_service->setOrUpdate(auth()->user()->therapist, $user, $request->all());
            return ApiHelper::validResponse("Treatment plan saved successfully", [
                "total_sessions" => $plan->total_sessions,
                "progress_status" => $plan->progress_status,
            ]);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (ModelNotFoundException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::NOT_FOUND_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
