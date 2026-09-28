<?php

namespace App\Http\Controllers\Api\V2\Therapist;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Exceptions\Payment\FlutterwaveException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\Therapist\PayoutService;
use Exception;

class PayoutController extends Controller
{
    public $payout_service;
    function __construct()
    {
        $this->payout_service = new PayoutService;
    }

    private function forbiddenUnlessTherapist()
    {
        if (empty(auth()->user()->therapist)) {
            return ApiHelper::problemResponse("Forbidden", ApiConstants::FORBIDDEN_ERR_CODE, null, null);
        }

        return null;
    }

    public function store()
    {
        if ($forbidden = $this->forbiddenUnlessTherapist()) {
            return $forbidden;
        }

        try {
            $payout = $this->payout_service->withdraw(auth()->user()->therapist);
            return ApiHelper::validResponse("Payout initiated successfully", [
                "id" => $payout->id,
                "amount" => $payout->amount,
                "status" => $payout->status,
                "provider_ref" => $payout->provider_ref,
            ]);
        } catch (InvalidRequestException | FlutterwaveException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function show($payout)
    {
        if ($forbidden = $this->forbiddenUnlessTherapist()) {
            return $forbidden;
        }

        try {
            $model = PayoutService::getOwned(auth()->user()->therapist, $payout);
            return ApiHelper::validResponse("Payout returned successfully", [
                "id" => $model->id,
                "amount" => $model->amount,
                "status" => $model->status,
                "provider_ref" => $model->provider_ref,
                "completed_at" => $model->completed_at?->toDateTimeString(),
            ]);
        } catch (ModelNotFoundException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::NOT_FOUND_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
