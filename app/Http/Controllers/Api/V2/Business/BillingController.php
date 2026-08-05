<?php

namespace App\Http\Controllers\Api\V2\Business;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\OrganizationInvoice;
use App\Services\Business\OrganizationBillingRunService;
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

    /**
     * Start the onboarding card checkout: create a pending payment for the
     * up-front session-bundle charge and hand back the config the web Flutterwave
     * inline modal needs. `amount` is 0 when there is nothing to charge now.
     */
    public function checkout(Request $request)
    {
        try {
            return ApiHelper::validResponse(
                "Checkout initiated successfully",
                OrganizationBillingService::bundleCheckout(
                    $request->attributes->get("organization"),
                    $request->user()
                )
            );
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    /**
     * Start the postpay card-on-file capture: create the verification payment and
     * hand back the Flutterwave inline config. The card is saved (and the auth
     * refunded) by the webhook; nothing is really charged now.
     */
    public function cardSetup(Request $request)
    {
        try {
            return ApiHelper::validResponse(
                "Card setup initiated",
                OrganizationBillingService::cardSetupCheckout(
                    $request->attributes->get("organization"),
                    $request->user()
                )
            );
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    /**
     * Reconcile a net-terms invoice as settled (offline bank transfer). Tenant-
     * scoped by reference within the caller's organization; admin-gated by the
     * route group. Returns the refreshed invoice list.
     */
    public function markInvoicePaid(Request $request, string $reference)
    {
        try {
            $organization = $request->attributes->get("organization");

            $invoice = OrganizationInvoice::where("organization_id", $organization->id)
                ->where("reference", $reference)
                ->first();

            if (empty($invoice)) {
                return ApiHelper::problemResponse("Invoice not found.", ApiConstants::NOT_FOUND_ERR_CODE, null, null);
            }

            OrganizationBillingRunService::markPaid($invoice);

            return ApiHelper::validResponse(
                "Invoice marked as paid",
                OrganizationBillingService::invoices($organization)
            );
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
