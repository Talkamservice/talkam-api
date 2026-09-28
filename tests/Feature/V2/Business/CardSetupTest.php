<?php

namespace Tests\Feature\V2\Business;

use App\Constants\Business\OrganizationConstants as OC;
use App\Constants\Finance\Payment\PaymentConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Payment;
use App\Models\User;
use App\Services\Business\OrganizationBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Postpay card-on-file capture (web §10): start the verification checkout and
 * save the tokenized card from the webhook.
 */
class CardSetupTest extends TestCase
{
    use RefreshDatabase;

    private function orgWithAdmin(array $overrides = []): array
    {
        $org = Organization::create(array_merge([
            "name" => "Meridian",
            "slug" => "meridian-" . uniqid(),
            "domain" => "meridian" . uniqid() . ".ng",
            "status" => OC::STATUS_ACTIVE,
            "seats_licensed" => 50,
            "payment_timing" => "postpay",
            "verified_at" => now(),
        ], $overrides));

        $admin = User::factory()->create();
        OrganizationMember::create([
            "organization_id" => $org->id,
            "user_id" => $admin->id,
            "role" => OC::ROLE_ADMIN,
            "status" => OC::MEMBER_ACTIVE,
            "activated_at" => now(),
        ]);

        return [$org, $admin];
    }

    private function pendingSetupPayment(Organization $org, User $user, array $meta = []): Payment
    {
        return Payment::create([
            "user_id" => $user->id,
            "currency" => "NGN",
            "amount" => 50,
            "reference" => "TK-CARD-" . strtoupper(uniqid()),
            "activity" => PaymentConstants::PAYMENT_FOR_CARD_SETUP,
            "description" => "card verification",
            "type" => PaymentConstants::DEBIT,
            "metadata" => array_merge(["organization_id" => $org->id], $meta),
            "status" => StatusConstants::PENDING,
        ]);
    }

    public function test_card_setup_creates_a_pending_verification_payment(): void
    {
        [$org, $admin] = $this->orgWithAdmin();
        Sanctum::actingAs($admin);

        $data = $this->postJson("/api/v2/business/organization/card/setup")->assertOk()->json("data");

        $this->assertEquals((int) config("business.card_setup_amount"), $data["amount"]);
        $this->assertStringStartsWith("TK-CARD-", $data["reference"]);
        $this->assertSame($admin->email, $data["customer"]["email"]);
        $this->assertSame(PaymentConstants::PAYMENT_FOR_CARD_SETUP, $data["meta"]["activity"]);
        $this->assertSame($org->id, $data["meta"]["organization_id"]);

        $this->assertDatabaseHas("payments", [
            "reference" => $data["reference"],
            "activity" => PaymentConstants::PAYMENT_FOR_CARD_SETUP,
            "status" => StatusConstants::PENDING,
        ]);
    }

    public function test_card_setup_requires_an_admin(): void
    {
        [$org] = $this->orgWithAdmin();
        $employee = User::factory()->create();
        OrganizationMember::create([
            "organization_id" => $org->id,
            "user_id" => $employee->id,
            "role" => OC::ROLE_EMPLOYEE,
            "status" => OC::MEMBER_ACTIVE,
            "activated_at" => now(),
        ]);
        Sanctum::actingAs($employee);

        $this->postJson("/api/v2/business/organization/card/setup")->assertForbidden();
    }

    public function test_save_card_stores_the_token_and_completes_the_payment(): void
    {
        [$org, $admin] = $this->orgWithAdmin();
        $payment = $this->pendingSetupPayment($org, $admin);

        OrganizationBillingService::saveCardOnFile($payment, "flw-t0-abc123", "4242", "VISA");

        $org->refresh();
        $this->assertSame("flw-t0-abc123", $org->card_token);
        $this->assertSame("4242", $org->card_last4);
        $this->assertSame("VISA", $org->card_brand);
        $this->assertNotNull($org->card_setup_at);
        $this->assertSame(StatusConstants::COMPLETED, $payment->refresh()->status);
    }

    public function test_save_card_rejects_a_missing_token(): void
    {
        [$org, $admin] = $this->orgWithAdmin();
        $payment = $this->pendingSetupPayment($org, $admin);

        $this->expectException(InvalidRequestException::class);
        OrganizationBillingService::saveCardOnFile($payment, null, "4242", "VISA");
    }
}
