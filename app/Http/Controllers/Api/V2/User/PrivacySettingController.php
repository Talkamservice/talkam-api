<?php

namespace App\Http\Controllers\Api\V2\User;

use App\Constants\General\ApiConstants;
use App\Exceptions\Auth\PinException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\User\PrivacySettingService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class PrivacySettingController extends Controller
{
    public $privacy_service;
    function __construct()
    {
        $this->privacy_service = new PrivacySettingService;
    }

    public function index()
    {
        try {
            $data = PrivacySettingService::forUser(auth()->user());
            return ApiHelper::validResponse("Privacy settings returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function store(Request $request)
    {
        try {
            $data = $this->privacy_service->update(auth()->user(), $request->all());
            return ApiHelper::validResponse("Privacy settings updated successfully", $data);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (PinException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
