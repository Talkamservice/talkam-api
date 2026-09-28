<?php

namespace App\Http\Controllers\Api\V2\User;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\User\PaymentMethodService;
use App\Services\User\PaymentPinService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class PaymentMethodController extends Controller
{
    public $payment_method_service;
    public $payment_pin_service;

    public function __construct()
    {
        $this->payment_method_service = new PaymentMethodService;
        $this->payment_pin_service = new PaymentPinService;
    }

    public function index()
    {
        try {
            $methods = PaymentMethodService::listFor(auth()->user())->get();
            return ApiHelper::validResponse("Payment methods returned successfully", $methods->toArray());
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function destroy($id)
    {
        try {
            $this->payment_method_service->delete(auth()->user(), $id);
            return ApiHelper::validResponse("Payment method removed successfully");
        } catch (ModelNotFoundException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::NOT_FOUND_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function setPaymentPin(Request $request)
    {
        try {
            $this->payment_pin_service->setOrChange(auth()->user(), $request->all());
            return ApiHelper::validResponse("Payment PIN saved successfully");
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (InvalidRequestException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
