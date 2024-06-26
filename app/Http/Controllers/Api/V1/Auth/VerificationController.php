<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Constants\General\ApiConstants;
use App\Exceptions\Auth\PinException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\Auth\VerifyService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class VerificationController extends Controller
{
    public $verify_service;
    function __construct()
    {
        $this->verify_service = new VerifyService;
    }
    public function request(Request $request)
    {
        try {
            $this->verify_service->request($request->all());
            return ApiHelper::validResponse("Verification pin sent successfully");
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function verify(Request $request)
    {
        try {
            $this->verify_service->verify($request->all());
            return ApiHelper::validResponse("Email verified successfully");
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (PinException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
