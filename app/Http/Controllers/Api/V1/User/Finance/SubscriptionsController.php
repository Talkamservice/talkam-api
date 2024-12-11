<?php

namespace App\Http\Controllers\Api\V1\User\Finance;

use App\Constants\General\ApiConstants;
use App\Exceptions\Finance\PlanException;
use App\Exceptions\Finance\SubscriptionException;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Finance\Payment\PaymentResource;
use App\Http\Resources\Finance\Subscription\SubscriptionResource;
use App\Services\Finance\Subscription\SubscriptionService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SubscriptionsController extends Controller
{
    protected $subscription_service;

    public function __construct()
    {
        $this->subscription_service = new SubscriptionService;
    }

    public function index(Request $request)
    {
        try {
            $subscriptions = $this->subscription_service->list()->get();
            $data = SubscriptionResource::collection($subscriptions);
            return ApiHelper::validResponse("Subscriptions returned successfully", $data);
        } catch (Exception $e) {
            //throw $th;
            return ApiHelper::problemResponse("Something went wrong while trying to process your request", ApiConstants::SERVER_ERR_CODE,  $request, $e);
        }
    }

    public function show(Request $request, $subscription_id)
    {
        try {
            $subscription = $this->subscription_service->getById($subscription_id);
            $data = SubscriptionResource::make($subscription);
            return ApiHelper::validResponse("Subscription returned successfully", $data);
        } catch (ModelNotFoundException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE,  $request, $e);
        } catch (Exception $e) {
            //throw $th;
            return ApiHelper::problemResponse("Something went wrong while trying to process your request", ApiConstants::SERVER_ERR_CODE,  $request, $e);
        }
    }

    public function initiate(Request $request)
    {
        try {
            $response = $this->subscription_service->setUser(auth()->user())->initiate($request->all());
            $data = PaymentResource::make($response);
            return ApiHelper::validResponse("Subscription initiated successfully", $data);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse("The given data is invalis", ApiConstants::BAD_REQ_ERR_CODE,  $request, $e);
        } catch (InvalidRequestException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE,  $request, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse("Something went wrong while trying to process your request", ApiConstants::SERVER_ERR_CODE,  $request, $e);
        }
    }

    public function cancel(Request $request, $subscription_id)
    {
        try {
            $subscription = $this->subscription_service->getById($subscription_id);
            $response = $this->subscription_service->cancel($subscription);
            $data = SubscriptionResource::make($response);
            return ApiHelper::validResponse("Subscription cancelled successfully", $data);
        } catch (InvalidRequestException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE,  $request, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse("Something went wrong while trying to process your request", ApiConstants::SERVER_ERR_CODE,  $request, $e);
        }
    }
}
