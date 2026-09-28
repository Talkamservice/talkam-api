<?php

namespace Tests\Feature\V2\Business;

use App\Constants\Business\OrganizationConstants as OC;
use App\Constants\Finance\Payment\PaymentConstants;
use App\Constants\General\StatusConstants;
use App\Models\Organization;
use App\Models\OrganizationInvoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Business\BundleLedgerService;
use App\Services\Business\OrganizationBillingRunService;
use App\Services\Business\OrganizationBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prepay activates on payment (web §08/§11): a prepaid bundle is usable only once
 * it's actually paid — by card at signup, or when the bank-transfer invoice reconciles.
 */
class BundleFundingTest extends TestCase
{
    use RefreshDatabase;

    private function prepayOrg(array $overrides = []): Organization
    {
        return Organization::create(array_merge([
            "name" => "Meridian Health",
            "slug" => "meridian-" . uniqid(),
            "domain" => "meridian" . uniqid() . ".ng",
            "status" => OC::STATUS_ACTIVE,
            "seats_licensed" => 5,               // tier 1 → ₦7,000/seat
            "therapist_access" => true,
            "session_bundle_sessions" => 10,     // 10 × ₦8,000 = ₦80,000
            "payment_timing" => "prepay",
            "verified_at" => now(),
        ], $overrides));
    }

    public function test_bundle_is_unusable_until_funded(): void
    {
        $org = $this->prepayOrg();

        // Purchased count is recorded, but nothing is funded yet → not usable.
        $this->assertNull($org->session_bundle_funded_at);
        $this->assertSame(0, BundleLedgerService::remaining($org));
    }

    public function test_card_payment_funds_the_bundle(): void
    {
        $org = $this->prepayOrg();
        $user = User::factory()->create();

        $payment = Payment::create([
            "user_id" => $user->id,
            "currency" => "NGN",
            "amount" => 115000,
            "reference" => "TK-BUNDLE-" . strtoupper(uniqid()),
            "activity" => PaymentConstants::PAYMENT_FOR_BUSINESS_BUNDLE,
            "description" => "first month + bundle",
            "type" => PaymentConstants::DEBIT,
            "metadata" => ["organization_id" => $org->id, "bundle_sessions" => 10],
            "status" => StatusConstants::PENDING,
        ]);

        OrganizationBillingService::fulfilBundlePayment($payment);

        $org->refresh();
        $this->assertNotNull($org->session_bundle_funded_at);       // funded on card charge
        $this->assertSame(10, BundleLedgerService::remaining($org)); // now usable

        $invoice = OrganizationInvoice::where("reference", $payment->reference)->first();
        $this->assertSame(OrganizationInvoice::STATUS_PAID, $invoice->status);
        $this->assertSame(10, (int) $invoice->bundle_sessions);
    }

    public function test_ensure_prepay_invoice_bills_seats_plus_bundle_and_is_idempotent(): void
    {
        $org = $this->prepayOrg();

        $invoice = OrganizationBillingService::ensurePrepayInvoice($org);

        $this->assertNotNull($invoice);
        $this->assertSame("INV-" . now()->format("Ym") . "-" . $org->id, $invoice->reference);
        $this->assertSame(OrganizationInvoice::STATUS_DUE, $invoice->status);
        $this->assertSame(10, (int) $invoice->bundle_sessions);
        $this->assertEquals(115000, (float) $invoice->amount); // 5×₦7,000 + 10×₦8,000

        // Idempotent — no second invoice.
        $again = OrganizationBillingService::ensurePrepayInvoice($org);
        $this->assertSame($invoice->id, $again->id);
        $this->assertSame(1, OrganizationInvoice::where("organization_id", $org->id)->count());
    }

    public function test_ensure_prepay_invoice_is_a_noop_for_postpay_no_bundle_or_funded(): void
    {
        $this->assertNull(OrganizationBillingService::ensurePrepayInvoice(
            $this->prepayOrg(["payment_timing" => "postpay"])
        ));
        $this->assertNull(OrganizationBillingService::ensurePrepayInvoice(
            $this->prepayOrg(["session_bundle_sessions" => 0])
        ));
        $this->assertNull(OrganizationBillingService::ensurePrepayInvoice(
            $this->prepayOrg(["session_bundle_funded_at" => now()])
        ));
    }

    public function test_billing_summary_shows_an_unfunded_bundle_as_pending_and_no_pay_method(): void
    {
        // Skipped billing: bundle purchased (10) but never paid, no pay method chosen.
        $org = $this->prepayOrg(["pay_method" => null]);

        $usage = OrganizationBillingService::usage($org);
        $this->assertFalse($usage["sessionsFunded"]);
        $this->assertSame(0, $usage["sessionsRemaining"]); // unpaid → nothing usable yet
        $this->assertSame(10, $usage["sessionsBundle"]);   // purchased count kept for context

        $plan = OrganizationBillingService::currentPlan($org);
        $this->assertNull($plan["payMethodLabel"]);        // skipped → "not set up"
        $this->assertFalse($plan["bundleFunded"]);
        $this->assertFalse($plan["billingReady"]);         // no payment path → setup incomplete
    }

    public function test_billing_ready_reflects_any_payment_path(): void
    {
        $this->assertFalse($this->prepayOrg(["pay_method" => null])->billingReady());       // skipped
        $this->assertTrue($this->prepayOrg(["card_token" => "flw-tok"])->billingReady());   // card on file
        $this->assertTrue($this->prepayOrg(["va_account_number" => "1234567890"])->billingReady()); // dedicated account
        $this->assertTrue($this->prepayOrg(["session_bundle_funded_at" => now()])->billingReady()); // paid bundle
    }

    public function test_billing_summary_reflects_a_funded_bundle_and_pay_method(): void
    {
        $org = $this->prepayOrg(["pay_method" => "invoice", "session_bundle_funded_at" => now()]);

        $usage = OrganizationBillingService::usage($org);
        $this->assertTrue($usage["sessionsFunded"]);
        $this->assertSame(10, $usage["sessionsRemaining"]);

        $plan = OrganizationBillingService::currentPlan($org);
        $this->assertSame("Bank transfer", $plan["payMethodLabel"]);
        $this->assertTrue($plan["bundleFunded"]);
        $this->assertTrue($plan["billingReady"]);
    }

    public function test_paying_the_transfer_invoice_funds_the_bundle(): void
    {
        $org = $this->prepayOrg();
        $invoice = OrganizationBillingService::ensurePrepayInvoice($org);
        $this->assertNull($org->refresh()->session_bundle_funded_at);

        // The dedicated-account transfer reconciles → invoice marked paid.
        OrganizationBillingRunService::markPaid($invoice);

        $org->refresh();
        $this->assertNotNull($org->session_bundle_funded_at);
        $this->assertSame(10, BundleLedgerService::remaining($org));
    }
}
