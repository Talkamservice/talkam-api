<?php

namespace App\Http\Controllers\Admin\Therapist;

use App\Constants\General\ApiConstants;
use App\Constants\General\AppConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\TherapistApplication;
use App\Services\Therapist\TherapistReviewService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

/**
 * Admin lane: therapist application review (JSON endpoints).
 */
class TherapistReviewController extends Controller
{
    public $review_service;

    public function __construct()
    {
        $this->review_service = new TherapistReviewService;

        $this->middleware(function ($request, $next) {
            if (!auth()->check() || !auth()->user()->isAdmin()) {
                return response()->json([
                    "message" => "Forbidden",
                    "success" => false,
                    "code" => ApiConstants::FORBIDDEN_ERR_CODE,
                ], ApiConstants::FORBIDDEN_ERR_CODE);
            }
            return $next($request);
        });
    }

    public function index(Request $request)
    {
        try {
            $applications = TherapistApplication::with("user")
                ->when($request->status, fn ($q, $status) => $q->where("status", $status))
                ->latest()
                ->paginate(AppConstants::API_PAGINATION_SIZE);

            $data = collectPagination($applications);
            $data["data"] = $applications->getCollection()->map(fn ($application) => [
                "id" => $application->id,
                "user_id" => $application->user_id,
                "applicant" => $application->user?->full_name,
                "status" => $application->status,
                "credential_type" => $application->credential_type,
                "submitted_at" => $application->submitted_at?->toDateTimeString(),
            ]);

            return ApiHelper::validResponse("Applications returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function show($id)
    {
        try {
            $application = TherapistReviewService::getApplication($id);
            $application->load(["user", "documents", "specialties.category"]);
            return ApiHelper::validResponse("Application returned successfully", $application->toArray());
        } catch (ModelNotFoundException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function approve($id)
    {
        try {
            $application = $this->review_service->approve($id);
            return ApiHelper::validResponse("Application approved successfully", [
                "status" => $application->status,
            ]);
        } catch (ModelNotFoundException | InvalidRequestException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function reject(Request $request, $id)
    {
        try {
            $application = $this->review_service->reject($id, $request->all());
            return ApiHelper::validResponse("Application rejected", [
                "status" => $application->status,
                "rejection_reason" => $application->rejection_reason,
            ]);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (ModelNotFoundException | InvalidRequestException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function documentVerdict(Request $request, $id)
    {
        try {
            $document = $this->review_service->documentVerdict($id, $request->all());
            return ApiHelper::validResponse("Document verdict saved", [
                "id" => $document->id,
                "status" => $document->status,
                "rejection_reason" => $document->rejection_reason,
            ]);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (ModelNotFoundException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
