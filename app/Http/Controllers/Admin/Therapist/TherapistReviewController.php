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
        // If request expects JSON (API call), return JSON response
        if ($request->expectsJson() || $request->header('Accept') === 'application/json') {
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

        // Otherwise, return web view
        $query = TherapistApplication::with('user');

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('first_name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Date range filter
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $applications = $query->latest()->paginate(15);

        // Statistics
        $statistics = [
            'total' => TherapistApplication::count(),
            'draft' => TherapistApplication::where('status', 'draft')->count(),
            'submitted' => TherapistApplication::whereIn('status', ['submitted', 'in_review'])->count(),
            'approved' => TherapistApplication::where('status', 'approved')->count(),
            'rejected' => TherapistApplication::where('status', 'rejected')->count(),
        ];

        return view('dashboards.admin.pages.therapist.applications', compact('applications', 'statistics'));
    }

    public function show($id)
    {
        // If request expects JSON (API call), return JSON response
        if (request()->expectsJson() || request()->header('Accept') === 'application/json') {
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

        // Otherwise, return web view
        $application = TherapistApplication::with(['user', 'documents', 'specialties.category'])
            ->findOrFail($id);

        return view('dashboards.admin.pages.therapist.show', compact('application'));
    }

    public function approve($id)
    {
        try {
            $application = $this->review_service->approve($id);
            
            // Return JSON for API calls
            if (request()->expectsJson() || request()->header('Accept') === 'application/json') {
                return ApiHelper::validResponse("Application approved successfully", [
                    "status" => $application->status,
                ]);
            }
            
            // Return redirect for web
            return redirect()->route('admin.therapist-applications.index')->with(
                'success_message',
                'Application approved successfully! Therapist account has been created.'
            );
        } catch (ModelNotFoundException | InvalidRequestException $e) {
            if (request()->expectsJson() || request()->header('Accept') === 'application/json') {
                return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
            }
            return back()->with('error_message', $e->getMessage());
        } catch (Exception $e) {
            if (request()->expectsJson() || request()->header('Accept') === 'application/json') {
                return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
            }
            return back()->with('error_message', 'An error occurred while approving the application.');
        }
    }

    public function reject(Request $request, $id)
    {
        try {
            $application = $this->review_service->reject($id, $request->all());
            
            // Return JSON for API calls
            if ($request->expectsJson() || $request->header('Accept') === 'application/json') {
                return ApiHelper::validResponse("Application rejected", [
                    "status" => $application->status,
                    "rejection_reason" => $application->rejection_reason,
                ]);
            }
            
            // Return redirect for web
            return redirect()->route('admin.therapist-applications.index')->with(
                'success_message',
                'Application rejected successfully.'
            );
        } catch (ValidationException $e) {
            if ($request->expectsJson() || $request->header('Accept') === 'application/json') {
                return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
            }
            return back()->withErrors($e->validator)->withInput();
        } catch (ModelNotFoundException | InvalidRequestException $e) {
            if ($request->expectsJson() || $request->header('Accept') === 'application/json') {
                return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
            }
            return back()->with('error_message', $e->getMessage());
        } catch (Exception $e) {
            if ($request->expectsJson() || $request->header('Accept') === 'application/json') {
                return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
            }
            return back()->with('error_message', 'An error occurred while rejecting the application.');
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
