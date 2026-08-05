<?php

namespace App\Http\Controllers\Api\V2\Business;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\OrganizationInvoice;
use App\Services\Business\OrganizationBillingRunService;
use App\Services\Business\OrganizationBillingService;
use App\Services\Business\VirtualAccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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
                // Bank-transfer reconciliation (web §11): the org's dedicated account
                // (null until set up) + whether setup is available at all.
                "virtual_account" => VirtualAccountService::publicView($organization),
                "virtual_accounts_enabled" => (bool) config("business.virtual_accounts_enabled"),
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
     * Set up the org's dedicated bank-transfer account (web §11). Admin-gated by
     * the route group. Requires a director's BVN/NIN + consent — passed to
     * Flutterwave to mint the account and NEVER stored raw. Flag-gated.
     */
    public function createVirtualAccount(Request $request)
    {
        try {
            if (!config("business.virtual_accounts_enabled")) {
                return ApiHelper::problemResponse(
                    "Bank-transfer account setup isn't available yet.",
                    ApiConstants::BAD_REQ_ERR_CODE,
                    null,
                    null
                );
            }

            $validator = Validator::make($request->all(), [
                "id_type" => ["required", Rule::in(config("business.kyc_id_types"))],
                "id_number" => ["required", "digits:11"],
                "consent" => ["accepted"],
            ]);

            if ($validator->fails()) {
                return ApiHelper::problemResponse(
                    $validator->errors()->first(),
                    ApiConstants::VALIDATION_ERR_CODE,
                    null,
                    null
                );
            }

            $organization = VirtualAccountService::create(
                $request->attributes->get("organization"),
                $request->user(),
                $request->input("id_type"),
                $request->input("id_number")
            );

            return ApiHelper::validResponse(
                "Bank-transfer account ready",
                VirtualAccountService::publicView($organization)
            );
        } catch (InvalidRequestException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
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
