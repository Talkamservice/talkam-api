<?php

namespace App\Http\Controllers\Api\V1\User\Payment;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Finance\Payment\PaymentResource;
use App\Services\Finance\Payment\PaymentService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    protected $payment_service;

    public function __construct()
    {
        $this->payment_service = new PaymentService;
    }

    public function index(Request $request)
    {
        try {
            $promotions = $this->payment_service->list($request->all())->where("user_id", auth()->id())->get();
            $data = PaymentResource::collection($promotions);
            return ApiHelper::validResponse("Promotions returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function show($id)
    {
        try {
            $payment = $this->payment_service->getById($id);
            $data = PaymentResource::make($payment);
            return ApiHelper::validResponse("Payment details returned successfully", $data);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }

    public function callback(Request $request)
    {
        try {
            $payment = $this->payment_service->callback($request->all());
            $data = PaymentResource::make($payment);
            return ApiHelper::validResponse("Payment initiated successfully", $data);
        } catch (ValidationException $th) {
            return ApiHelper::inputErrorResponse("The given data", ApiConstants::VALIDATION_ERR_CODE, null, $th);
        } catch (ModelNotFoundException | InvalidRequestException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }

    public function delete($id)
    {
        try {
            $payment = $this->payment_service->getById($id);
            $payment->delete();
            return ApiHelper::validResponse("Payment deleted successfully");
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }
}
