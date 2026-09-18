<?php

namespace App\Http\Controllers\Api\V2\Finance;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\Finance\Payment\PaymentService;
use App\Services\Therapist\SessionPaymentHandlerService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

/**
 * v2 callback: wraps the shared v1 PaymentService::callback. On failed
 * verification of a session payment, the booking is marked failed so the
 * slot frees immediately (v1 behavior untouched on the v1 route).
 */
class PaymentCallbackController extends Controller
{
    public $payment_service;
    function __construct()
    {
        $this->payment_service = new PaymentService;
    }

    public function callback(Request $request)
    {
        try {
            $result = $this->payment_service->callback($request->all());
            return ApiHelper::validResponse("Payment processed successfully", $result);
        } catch (ValidationException $th) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $th);
        } catch (ModelNotFoundException | InvalidRequestException $th) {
            SessionPaymentHandlerService::markFailedByReference($request->input("reference"));
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            SessionPaymentHandlerService::markFailedByReference($request->input("reference"));
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }
}
