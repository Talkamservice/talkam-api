<?php

namespace Tests\Feature\V2\Business;

use App\Constants\Business\OrganizationConstants;
use App\Constants\Finance\Payment\PaymentConstants;
use App\Constants\General\StatusConstants;
use App\Models\Organization;
use App\Models\OrganizationInvoice;
use App\Models\OrganizationMember;
use App\Models\Payment;
use App\Models\User;
use App\Services\Business\OrganizationBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The onboarding card checkout (web §07): create the session-bundle payment and
 * fulfil it on a successful Flutterwave webhook.
 */
class BundleCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function orgWithAdmin(array $overrides = []): array
    {
        $org = Organization::create(array_merge([
            "name" => "Meridian Health",
            "slug" => "meridian-" . uniqid(),
            "domain" => "meridian" . uniqid() . ".ng",
            "status" => OrganizationConstants::STATUS_ACTIVE,
            "seats_licensed" => 50,
            "therapist_access" => true,
            "session_bundle_sessions" => 25,
            "verified_at" => now(),
        ], $overrides));

        $admin = User::factory()->create();
        OrganizationMember::create([
            "organization_id" => $org->id,
            "user_id" => $admin->id,
            "role" => OrganizationConstants::ROLE_ADMIN,
            "status" => OrganizationConstants::MEMBER_ACTIVE,
            "activated_at" => now(),
        ]);

        return [$org, $admin];
    }

    public function test_checkout_creates_a_pending_bundle_payment(): void
    {
        [$org, $admin] = $this->orgWithAdmin();
        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/v2/business/organization/plan/checkout");

        $response->assertOk();
        $data = $response->json("data");

        // First-month seats (50 × ₦7,000 = ₦350,000) + bundle (25 × ₦8,000 = ₦200,000).
        $this->assertEquals(550000, $data["amount"]);
        $this->assertSame(350000, $data["meta"]["seats_charge"]);
        $this->assertSame(200000, $data["meta"]["bundle_charge"]);
        $this->assertStringStartsWith("TK-BUNDLE-", $data["reference"]);
        $this->assertSame($admin->email, $data["customer"]["email"]);
        $this->assertSame(PaymentConstants::PAYMENT_FOR_BUSINESS_BUNDLE, $data["meta"]["activity"]);
        $this->assertSame($org->id, $data["meta"]["organization_id"]);

        $this->assertDatabaseHas("payments", [
            "reference" => $data["reference"],
            "activity" => PaymentConstants::PAYMENT_FOR_BUSINESS_BUNDLE,
            "status" => StatusConstants::PENDING,
            "amount" => 550000,
        ]);
    }

    public function test_checkout_uses_the_custom_rate_for_a_custom_bundle(): void
    {
        [$org, $admin] = $this->orgWithAdmin([
            "session_bundle_sessions" => 18,
            "bundle_custom" => true,
        ]);
        Sanctum::actingAs($admin);

        $data = $this->postJson("/api/v2/business/organization/plan/checkout")->json("data");

        // First-month seats (₦350,000) + custom bundle (18 × ₦8,240 = ₦148,320) = ₦498,320.
        $this->assertEquals(498320, $data["amount"]);
        $this->assertSame(148320, $data["meta"]["bundle_charge"]);
        $this->assertDatabaseHas("payments", [
            "reference" => $data["reference"],
            "amount" => 498320,
        ]);
    }

    public function test_checkout_charges_nothing_at_signup_for_postpay(): void
    {
        [$org, $admin] = $this->orgWithAdmin([
            "payment_timing" => "postpay",
            "session_bundle_sessions" => 0,
        ]);
        Sanctum::actingAs($admin);

        $data = $this->postJson("/api/v2/business/organization/plan/checkout")->json("data");

        $this->assertEquals(0, $data["amount"]);
        $this->assertNull($data["reference"]);
        $this->assertDatabaseCount("payments", 0);
    }

    public function test_checkout_requires_an_admin(): void
    {
        [$org] = $this->orgWithAdmin();
        $employee = User::factory()->create();
        OrganizationMember::create([
            "organization_id" => $org->id,
            "user_id" => $employee->id,
            "role" => OrganizationConstants::ROLE_EMPLOYEE,
            "status" => OrganizationConstants::MEMBER_ACTIVE,
            "activated_at" => now(),
        ]);
        Sanctum::actingAs($employee);

        $this->postJson("/api/v2/business/organization/plan/checkout")->assertForbidden();
    }

    /* ── Fulfilment (webhook) ───────────────────────────────────────────── */

    private function pendingBundlePayment(Organization $org, User $user): Payment
    {
        return Payment::create([
            "user_id" => $user->id,
            "currency" => "NGN",
            "amount" => 200000,
            "reference" => "TK-BUNDLE-TEST123",
            "activity" => PaymentConstants::PAYMENT_FOR_BUSINESS_BUNDLE,
            "description" => "Session bundle",
            "type" => PaymentConstants::DEBIT,
            "metadata" => [
                "activity" => PaymentConstants::PAYMENT_FOR_BUSINESS_BUNDLE,
                "organization_id" => $org->id,
                "bundle_sessions" => 25,
            ],
            "status" => StatusConstants::PENDING,
        ]);
    }

    public function test_fulfilment_completes_the_payment_and_records_a_paid_invoice(): void
    {
        [$org, $admin] = $this->orgWithAdmin();
        $payment = $this->pendingBundlePayment($org, $admin);

        OrganizationBillingService::fulfilBundlePayment($payment);

        $this->assertSame(StatusConstants::COMPLETED, $payment->refresh()->status);
        $this->assertDatabaseHas("organization_invoices", [
            "reference" => "TK-BUNDLE-TEST123",
            "organization_id" => $org->id,
            "amount" => 200000,
            "status" => OrganizationInvoice::STATUS_PAID,
        ]);
    }

    public function test_fulfilment_is_idempotent(): void
    {
        [$org, $admin] = $this->orgWithAdmin();
        $payment = $this->pendingBundlePayment($org, $admin);

        OrganizationBillingService::fulfilBundlePayment($payment);
        OrganizationBillingService::fulfilBundlePayment($payment->refresh());

        $this->assertSame(1, OrganizationInvoice::where("reference", "TK-BUNDLE-TEST123")->count());
    }
}
