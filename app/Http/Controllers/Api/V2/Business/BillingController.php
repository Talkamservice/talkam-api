<?php

namespace App\Http\Controllers\Api\V2\Business;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\Business\OrganizationBillingService;
use Illuminate\Http\Request;
use Exception;

/**
 * The admin Billing screen (web §07). Admin-gated and tenant-scoped by the
 * org.role middleware, which puts the caller's organization on the request — no
 * endpoint here ever takes an organization id from the client.
 */
class BillingController extends Controller
{
    public function summary(Request $request)
    {
        try {
            $organization = $request->attributes->get("organization");

            return ApiHelper::validResponse("Billing returned successfully", [
                "current_plan" => OrganizationBillingService::currentPlan($organization),
                "usage" => OrganizationBillingService::usage($organization),
                "current_seats" => (int) $organization->seats_licensed,
                "catalogue" => OrganizationBillingService::catalogue($organization),
            ]);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function invoices(Request $request)
    {
        try {
            return ApiHelper::validResponse(
                "Invoices returned successfully",
                OrganizationBillingService::invoices($request->attributes->get("organization"))
            );
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
