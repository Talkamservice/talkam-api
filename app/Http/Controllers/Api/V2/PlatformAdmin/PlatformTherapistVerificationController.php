<?php

namespace App\Http\Controllers\Api\V2\PlatformAdmin;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\TherapistApplication;
use App\Services\PlatformAdmin\PlatformTherapistVerificationService;
use App\Services\Therapist\TherapistReviewService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class PlatformTherapistVerificationController extends Controller
{
    public function index(Request $request)
    {
        try {
            $page = max(1, (int) $request->input('page', 1));
            return ApiHelper::validResponse("Applications returned successfully", [
                "applications" => PlatformTherapistVerificationService::list($request->input('tab'), $page),
                "overview" => PlatformTherapistVerificationService::overview(),
            ]);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function show($id)
    {
        try {
            $application = TherapistApplication::with(['user', 'documents.file'])->findOrFail($id);
            return ApiHelper::validResponse("Application returned successfully", $application);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function startReview($id)
    {
        try {
            return ApiHelper::validResponse("Application moved to review", PlatformTherapistVerificationService::startReview((int) $id));
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function approve($id)
    {
        try {
            $application = (new TherapistReviewService)->approve($id);
            return ApiHelper::validResponse("Application approved", $application);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function reject(Request $request, $id)
    {
        try {
            $application = (new TherapistReviewService)->reject($id, $request->only('reason'));
            return ApiHelper::validResponse("Application rejected", $application);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function documentVerdict(Request $request, $documentId)
    {
        try {
            $document = (new TherapistReviewService)->documentVerdict($documentId, $request->only(['status', 'reason']));
            return ApiHelper::validResponse("Document reviewed", $document);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }
}
