<?php

namespace App\Http\Controllers\Api\V2\User;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\User\OnboardingService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class OnboardingController extends Controller
{
    public $onboarding_service;
    function __construct()
    {
        $this->onboarding_service = new OnboardingService;
    }

    public function userType(Request $request)
    {
        try {
            $user = $this->onboarding_service->setUserType(auth()->user(), $request->all());
            return ApiHelper::validResponse("User type saved successfully", [
                "user_type" => $user->user_type,
            ]);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function complete()
    {
        try {
            $user = $this->onboarding_service->complete(auth()->user());
            return ApiHelper::validResponse("Onboarding completed successfully", [
                "onboarding" => OnboardingService::state($user),
            ]);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
