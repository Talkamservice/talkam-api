<?php

namespace App\Http\Controllers\Api\V2\Therapist;

use App\Constants\General\ApiConstants;
use App\Exceptions\Payment\FlutterwaveException;
use App\Exceptions\General\InvalidRequestException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\Therapist\TherapistPayoutService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class TherapistPayoutController extends Controller
{
    public $payout_service;
    function __construct()
    {
        $this->payout_service = new TherapistPayoutService;
    }

    public function banks()
    {
        try {
            $banks = TherapistPayoutService::banks();
            return ApiHelper::validResponse("Banks returned successfully", $banks);
        } catch (FlutterwaveException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function verify(Request $request)
    {
        try {
            $result = $this->payout_service->verify($request->all());
            return ApiHelper::validResponse("Account resolved successfully", $result);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (FlutterwaveException $e) {
            return ApiHelper::problemResponse("Invalid Account No.", ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function payout(Request $request)
    {
        try {
            $account = $this->payout_service->savePayout(auth()->user(), $request->all());
            return ApiHelper::validResponse("Payout details saved successfully", [
                "bank_name" => $account->bank_name,
                "account_number" => $account->account_number,
                "account_name" => $account->account_name,
                "verified_at" => $account->verified_at?->toDateTimeString(),
            ]);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (FlutterwaveException $e) {
            return ApiHelper::problemResponse("Invalid Account No.", ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (InvalidRequestException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
