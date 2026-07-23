<?php

namespace App\Http\Controllers\Api\V2\Therapist;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\Therapist\TherapistApplicationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class TherapistApplicationController extends Controller
{
    public $application_service;
    function __construct()
    {
        $this->application_service = new TherapistApplicationService;
    }

    public function show()
    {
        try {
            $data = TherapistApplicationService::state(auth()->user());
            return ApiHelper::validResponse("Application state returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function personal(Request $request)
    {
        try {
            $application = $this->application_service->savePersonal(auth()->user(), $request->all());
            return ApiHelper::validResponse("Personal details saved successfully", [
                "application_id" => $application->id,
                "status" => $application->status,
                "steps" => TherapistApplicationService::stepCompleteness($application),
            ]);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (InvalidRequestException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function storeDocument(Request $request)
    {
        try {
            $document = $this->application_service->saveDocument(auth()->user(), $request->all());
            return ApiHelper::validResponse("Document uploaded successfully", [
                "id" => $document->id,
                "type" => $document->type,
                "status" => $document->status,
                "expires_at" => $document->expires_at,
            ]);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (InvalidRequestException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function deleteDocument($id)
    {
        try {
            $this->application_service->deleteDocument(auth()->user(), $id);
            return ApiHelper::validResponse("Document removed successfully");
        } catch (ModelNotFoundException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::NOT_FOUND_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function specialties(Request $request)
    {
        try {
            $application = $this->application_service->saveSpecialties(auth()->user(), $request->all());
            return ApiHelper::validResponse("Specialties saved successfully", [
                "bio" => $application->bio,
                "specialties" => $application->specialties()->pluck("category_id"),
            ]);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (InvalidRequestException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function availability(Request $request)
    {
        try {
            $application = $this->application_service->saveAvailability(auth()->user(), $request->all());
            return ApiHelper::validResponse("Availability saved successfully", [
                "session_duration" => $application->session_duration,
                "buffer_minutes" => $application->buffer_minutes,
            ]);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (InvalidRequestException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function submit()
    {
        try {
            $application = $this->application_service->submit(auth()->user());
            return ApiHelper::validResponse("Application submitted successfully", [
                "status" => $application->status,
                "submitted_at" => $application->submitted_at?->toDateTimeString(),
            ]);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (InvalidRequestException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
