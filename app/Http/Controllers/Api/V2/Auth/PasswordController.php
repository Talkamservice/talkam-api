<?php

namespace App\Http\Controllers\Api\V2\Auth;

use App\Constants\General\ApiConstants;
use App\Exceptions\Auth\AuthException;
use App\Exceptions\Auth\PinException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\Auth\V2\PasswordService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class PasswordController extends Controller
{
    public $password_service;
    function __construct()
    {
        $this->password_service = new PasswordService;
    }

    public function forgotPassword(Request $request)
    {
        try {
            $this->password_service->sendPasswordResetPin($request->all());
            return ApiHelper::validResponse("Password request sent successfully!");
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (AuthException | PinException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse(
                $this->serverErrorMessage,
                ApiConstants::SERVER_ERR_CODE,
                null,
                $e
            );
        }
    }

    public function resetPassword(Request $request)
    {
        try {
            $this->password_service->resetPassword($request->all());
            return ApiHelper::validResponse("Password reset successfully");
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (AuthException | PinException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse(
                "Something went wrong while processing your request",
                ApiConstants::BAD_REQ_ERR_CODE,
                null,
                $e
            );
        }
    }
}
