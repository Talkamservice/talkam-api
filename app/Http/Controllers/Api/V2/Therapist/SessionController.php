<?php

namespace App\Http\Controllers\Api\V2\Therapist;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\Therapist\SessionLifecycleService;
use App\Services\Therapist\SessionRescheduleService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class SessionController extends Controller
{
    public $lifecycle_service;
    public $reschedule_service;

    public function __construct()
    {
        $this->lifecycle_service = new SessionLifecycleService;
        $this->reschedule_service = new SessionRescheduleService;
    }

    public function receipt($booking)
    {
        try {
            $data = SessionLifecycleService::receipt($booking, auth()->user());
            return ApiHelper::validResponse("Receipt returned successfully", $data);
        } catch (ModelNotFoundException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::NOT_FOUND_ERR_CODE, null, $e);
        } catch (InvalidRequestException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function cancel(Request $request, $booking)
    {
        try {
            $session = $this->lifecycle_service->cancel(auth()->user(), $booking, $request->all());
            return ApiHelper::validResponse("Session cancelled successfully", [
                "status" => $session->status,
                "cancelled_by" => $session->cancelled_by,
            ]);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (ModelNotFoundException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::NOT_FOUND_ERR_CODE, null, $e);
        } catch (InvalidRequestException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function reschedule(Request $request, $booking)
    {
        try {
            $reschedule = $this->reschedule_service->request(auth()->user(), $booking, $request->all());
            return ApiHelper::validResponse("Reschedule requested successfully", [
                "id" => $reschedule->id,
                "status" => $reschedule->status,
                "new_starts_at" => $reschedule->new_starts_at->toDateTimeString(),
            ]);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (ModelNotFoundException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::NOT_FOUND_ERR_CODE, null, $e);
        } catch (InvalidRequestException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function respondToReschedule(Request $request, $id)
    {
        try {
            $reschedule = $this->reschedule_service->respond(auth()->user(), $id, $request->all());
            return ApiHelper::validResponse("Response recorded successfully", [
                "id" => $reschedule->id,
                "status" => $reschedule->status,
            ]);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (ModelNotFoundException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::NOT_FOUND_ERR_CODE, null, $e);
        } catch (InvalidRequestException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function join($booking)
    {
        try {
            $data = $this->lifecycle_service->join(auth()->user(), $booking);
            return ApiHelper::validResponse("Join details returned successfully", $data);
        } catch (ModelNotFoundException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::NOT_FOUND_ERR_CODE, null, $e);
        } catch (InvalidRequestException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function message($booking)
    {
        try {
            $data = $this->lifecycle_service->startConversation(auth()->user(), $booking);
            return ApiHelper::validResponse("Conversation ready", $data);
        } catch (ModelNotFoundException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::NOT_FOUND_ERR_CODE, null, $e);
        } catch (InvalidRequestException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
