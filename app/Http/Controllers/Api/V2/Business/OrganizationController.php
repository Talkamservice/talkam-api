<?php

namespace App\Http\Controllers\Api\V2\Business;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Business\OrganizationResource;
use App\Models\Industry;
use App\Services\Business\BenchTopicService;
use App\Services\Business\OrganizationPricingService;
use App\Services\Business\OrganizationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

/**
 * Company account setup — seats, plan and therapist bench (screens 3, 4, 4b).
 *
 * The organization is always taken from the request attributes set by the
 * org.role middleware, never from the payload: there is no way for a client to
 * name which company it is editing.
 */
class OrganizationController extends Controller
{
    public OrganizationService $organization_service;

    public function __construct()
    {
        $this->organization_service = new OrganizationService;
    }

    /** Public: the static half of the pricing contract the seats/plan screens render. */
    public function pricingConfig()
    {
        try {
            return ApiHelper::validResponse(
                "Pricing configuration returned successfully",
                OrganizationPricingService::config()
            );
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    /** Public: the admin-managed industry list the signup form renders. */
    public function industries()
    {
        try {
            $industries = Industry::active()
                ->orderBy("sort_order")
                ->orderBy("name")
                ->get(["id", "name", "slug"]);

            return ApiHelper::validResponse("Industries returned successfully", $industries);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function show(Request $request)
    {
        try {
            $organization = $request->attributes->get("organization");
            $this->authorize("view", $organization);

            return ApiHelper::validResponse("Organization returned successfully", [
                "organization" => OrganizationResource::make($organization)->resolve(),
                "quote" => OrganizationPricingService::quoteFor($organization),
                "bench" => [
                    "topics" => $organization->bench_topics ?? [],
                    "available" => BenchTopicService::activeList(),
                    "verified_therapist_count" => OrganizationPricingService::benchTherapistCount(),
                ],
            ]);
        } catch (Exception $e) {
            return $this->failure($e);
        }
    }

    public function seats(Request $request)
    {
        try {
            $organization = $request->attributes->get("organization");
            $this->authorize("update", $organization);

            $organization = $this->organization_service->saveSeats($organization, $request->all());

            return ApiHelper::validResponse("Seats saved successfully", [
                "organization" => OrganizationResource::make($organization)->resolve(),
                "quote" => OrganizationPricingService::quoteFor($organization),
            ]);
        } catch (Exception $e) {
            return $this->failure($e);
        }
    }

    public function plan(Request $request)
    {
        try {
            $organization = $request->attributes->get("organization");
            $this->authorize("update", $organization);

            $organization = $this->organization_service->savePlan($organization, $request->all());

            return ApiHelper::validResponse("Plan saved successfully", [
                "organization" => OrganizationResource::make($organization)->resolve(),
                "quote" => OrganizationPricingService::quoteFor($organization),
                "plan" => config("business.plan"),
                "bank_details" => config("business.bank_details"),
            ]);
        } catch (Exception $e) {
            return $this->failure($e);
        }
    }

    public function bench(Request $request)
    {
        try {
            $organization = $request->attributes->get("organization");
            $this->authorize("update", $organization);

            $organization = $this->organization_service->saveBench($organization, $request->all());

            return ApiHelper::validResponse("Therapist bench saved successfully", [
                "organization" => OrganizationResource::make($organization)->resolve(),
                "bench" => [
                    "topics" => $organization->bench_topics ?? [],
                    "available" => BenchTopicService::activeList(),
                    "verified_therapist_count" => OrganizationPricingService::benchTherapistCount(),
                ],
            ]);
        } catch (Exception $e) {
            return $this->failure($e);
        }
    }

    public function sessionPolicy(Request $request)
    {
        try {
            $organization = $request->attributes->get("organization");
            $this->authorize("update", $organization);

            $organization = $this->organization_service->saveSessionPolicy($organization, $request->all());

            return ApiHelper::validResponse("Session policy saved successfully", [
                "organization" => OrganizationResource::make($organization)->resolve(),
            ]);
        } catch (Exception $e) {
            return $this->failure($e);
        }
    }

    /* ── Danger Zone ──────────────────────────────────────────────────── */

    public function employeeAccess(Request $request)
    {
        try {
            $organization = $request->attributes->get("organization");
            $this->authorize("update", $organization);

            $organization = $this->organization_service->toggleEmployeeSuspension($organization, $request->all());

            return ApiHelper::validResponse("Employee access updated successfully", [
                "organization" => OrganizationResource::make($organization)->resolve(),
            ]);
        } catch (Exception $e) {
            return $this->failure($e);
        }
    }

    public function cancelSubscription(Request $request)
    {
        try {
            $organization = $request->attributes->get("organization");
            $this->authorize("update", $organization);

            $organization = $this->organization_service->cancelSubscription($organization);

            return ApiHelper::validResponse("Subscription cancellation scheduled", [
                "organization" => OrganizationResource::make($organization)->resolve(),
            ]);
        } catch (Exception $e) {
            return $this->failure($e);
        }
    }

    public function resumeSubscription(Request $request)
    {
        try {
            $organization = $request->attributes->get("organization");
            $this->authorize("update", $organization);

            $organization = $this->organization_service->resumeSubscription($organization);

            return ApiHelper::validResponse("Subscription cancellation reversed", [
                "organization" => OrganizationResource::make($organization)->resolve(),
            ]);
        } catch (Exception $e) {
            return $this->failure($e);
        }
    }

    public function requestDeletion(Request $request)
    {
        try {
            $organization = $request->attributes->get("organization");
            $this->authorize("update", $organization);

            $organization = $this->organization_service->requestDeletion($organization, $request->all());

            return ApiHelper::validResponse("Company account deletion scheduled", [
                "organization" => OrganizationResource::make($organization)->resolve(),
            ]);
        } catch (Exception $e) {
            return $this->failure($e);
        }
    }

    public function cancelDeletion(Request $request)
    {
        try {
            $organization = $request->attributes->get("organization");
            $this->authorize("update", $organization);

            $organization = $this->organization_service->cancelDeletion($organization);

            return ApiHelper::validResponse("Company account deletion cancelled", [
                "organization" => OrganizationResource::make($organization)->resolve(),
            ]);
        } catch (Exception $e) {
            return $this->failure($e);
        }
    }

    private function failure(Exception $e)
    {
        if ($e instanceof ValidationException) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        }

        if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
            return ApiHelper::problemResponse("You do not have permission to perform this action.", ApiConstants::FORBIDDEN_ERR_CODE, null, null);
        }

        if ($e instanceof InvalidRequestException) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, null);
        }

        return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
    }
}
