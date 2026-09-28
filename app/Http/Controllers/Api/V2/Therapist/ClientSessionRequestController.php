<?php

namespace App\Http\Controllers\Api\V2\Therapist;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\Therapist\TherapistSessionRequestService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

/**
 * Client side of inbound session requests: submit a preferred-time request
 * when no real slot works yet, see the therapist's response, decline a
 * proposed time (confirming it is just paying via the normal booking flow).
 */
class ClientSessionRequestController extends Controller
{
    public $service;

    public function __construct()
    {
        $this->service = new TherapistSessionRequestService;
    }

    public function store(Request $request)
    {
        try {
            $result = $this->service->submit(auth()->user(), $request->all());
            return ApiHelper::validResponse("Request sent successfully", [
                "id" => $result->id,
                "status" => $result->status,
            ]);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function index()
    {
        try {
            $requests = TherapistSessionRequestService::forClient(auth()->user());
            return ApiHelper::validResponse("Requests returned successfully", ["requests" => $requests]);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function decline($id)
    {
        try {
            $result = $this->service->declineByClient(auth()->user(), $id);
            return ApiHelper::validResponse("Request declined", ["id" => $result->id, "status" => $result->status]);
        } catch (ModelNotFoundException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::NOT_FOUND_ERR_CODE, null, $e);
        } catch (InvalidRequestException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
