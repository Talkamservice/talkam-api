<?php

namespace App\Http\Controllers\Api\V2\Therapist;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\Therapist\TherapistAvailabilityService;
use App\Services\Therapist\TherapistDashboardService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

/**
 * The therapist dashboard aggregates: Home, Analytics, and the live weekly
 * Availability grid.
 *
 * All therapist-gated by the repo's forbiddenUnlessTherapist() idiom (§§06–16),
 * so a non-therapist gets a 403 before any lookup.
 */
class DashboardController extends Controller
{
    public TherapistAvailabilityService $availability_service;

    public function __construct()
    {
        $this->availability_service = new TherapistAvailabilityService;
    }

    private function forbiddenUnlessTherapist()
    {
        if (empty(auth()->user()->therapist)) {
            return ApiHelper::problemResponse("Forbidden", ApiConstants::FORBIDDEN_ERR_CODE, null, null);
        }

        return null;
    }

    public function home()
    {
        if ($forbidden = $this->forbiddenUnlessTherapist()) {
            return $forbidden;
        }

        try {
            return ApiHelper::validResponse(
                "Home returned successfully",
                TherapistDashboardService::home(auth()->user()->therapist)
            );
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function analytics(Request $request)
    {
        if ($forbidden = $this->forbiddenUnlessTherapist()) {
            return $forbidden;
        }

        try {
            return ApiHelper::validResponse(
                "Analytics returned successfully",
                TherapistDashboardService::analytics(
                    auth()->user()->therapist,
                    $request->input("range", "4w")
                )
            );
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function availability()
    {
        if ($forbidden = $this->forbiddenUnlessTherapist()) {
            return $forbidden;
        }

        try {
            return ApiHelper::validResponse(
                "Availability returned successfully",
                TherapistAvailabilityService::grid(auth()->user())
            );
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function updateAvailability(Request $request)
    {
        if ($forbidden = $this->forbiddenUnlessTherapist()) {
            return $forbidden;
        }

        try {
            $grid = $this->availability_service->replace(auth()->user(), $request->all());
            return ApiHelper::validResponse("Availability updated successfully", $grid);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    /**
     * Decline a request. This IS the §08 cancel with the therapist as actor —
     * SessionLifecycleService::cancel already stamps cancelled_by=therapist,
     * fires the refund and notifies the client with no reason attached, which
     * is exactly the deck's "declining is never shared with the client as a
     * reason". Reused rather than forked.
     */
    public function declineRequest($session)
    {
        if ($forbidden = $this->forbiddenUnlessTherapist()) {
            return $forbidden;
        }

        try {
            $result = (new \App\Services\Therapist\SessionLifecycleService)
                ->cancel(auth()->user(), $session, []);

            return ApiHelper::validResponse("Request declined", [
                "id" => $result->id,
                "status" => $result->status,
            ]);
        } catch (ModelNotFoundException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::NOT_FOUND_ERR_CODE, null, null);
        } catch (\App\Exceptions\General\InvalidRequestException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, null);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
