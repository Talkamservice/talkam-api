<?php

namespace Tests\Feature\V2\Business;

use App\Constants\Business\OrganizationConstants as OC;
use App\Exceptions\General\InvalidRequestException;
use App\Models\Organization;
use App\Models\OrganizationInvoice;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\Business\VirtualAccountService;
use App\Services\Finance\PaymentGateways\Flutterwave\FlutterwaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

/**
 * Dedicated NGN virtual accounts (web §11): mint per-org accounts (KYC pass-through,
 * no raw BVN/NIN stored) and auto-reconcile bank transfers against open invoices.
 */
class VirtualAccountTest extends TestCase
{
    use RefreshDatabase;

    private function orgWithAdmin(array $overrides = []): array
    {
        $org = Organization::create(array_merge([
            "name" => "Meridian Health",
            "slug" => "meridian-" . uniqid(),
            "domain" => "meridian" . uniqid() . ".ng",
            "status" => OC::STATUS_ACTIVE,
            "seats_licensed" => 50,
            "pay_method" => "invoice",
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

    private function dueInvoice(Organization $org, float $amount, string $ref, $issuedAt): OrganizationInvoice
    {
        return OrganizationInvoice::create([
            "organization_id" => $org->id,
            "reference" => $ref,
            "period_start" => $issuedAt->copy()->startOfMonth()->toDateString(),
            "period_end" => $issuedAt->copy()->endOfMonth()->toDateString(),
            "seats" => 50,
            "amount" => $amount,
            "status" => OrganizationInvoice::STATUS_DUE,
            "issued_at" => $issuedAt,
            "due_at" => $issuedAt->copy()->addDays(14),
        ]);
    }

    /* ── Account creation / storage ─────────────────────────────────────── */

    public function test_store_account_persists_handles_and_minimal_kyc_only(): void
    {
        [$org] = $this->orgWithAdmin();

        VirtualAccountService::storeAccount(
            $org,
            ["account_number" => "1234567890", "bank_name" => "Flutterwave MFB", "order_ref" => "ord-xyz"],
            "bvn",
            "6789",
            "TK-VA-1-ABCD"
        );

        $org->refresh();
        $this->assertSame("1234567890", $org->va_account_number);
        $this->assertSame("Flutterwave MFB", $org->va_bank_name);
        $this->assertSame("ord-xyz", $org->va_reference);
        $this->assertSame("TK-VA-1-ABCD", $org->va_tx_ref);
        $this->assertSame("bvn", $org->kyc_id_type);
        $this->assertSame("6789", $org->kyc_id_last4);
        $this->assertNotNull($org->kyc_consent_at);

        // The raw 11-digit id is never persisted — no attribute holds it.
        foreach ($org->getAttributes() as $value) {
            $this->assertNotSame("22233344455", (string) $value);
        }
    }

    public function test_create_mints_the_account_and_never_stores_the_raw_id(): void
    {
        config(["business.virtual_accounts_enabled" => true]);
        [$org, $admin] = $this->orgWithAdmin();

        $mock = Mockery::mock(FlutterwaveService::class);
        $mock->shouldReceive("createVirtualAccount")
            ->once()
            ->with(Mockery::on(function ($payload) {
                // Pass-through: is_permanent + the raw BVN reach Flutterwave.
                return ($payload["is_permanent"] ?? null) === true
                    && ($payload["bvn"] ?? null) === "22233344455";
            }))
            ->andReturn(["account_number" => "9988776655", "bank_name" => "Flutterwave MFB", "order_ref" => "ord-1"]);
        $this->app->instance(FlutterwaveService::class, $mock);

        $org = VirtualAccountService::create($org, $admin, "bvn", "222-333-444-55");

        $this->assertSame("9988776655", $org->va_account_number);
        $this->assertSame("55", substr($org->kyc_id_last4, -2));

        // No organizations column contains the full BVN.
        $this->assertDatabaseMissing("organizations", ["kyc_id_last4" => "22233344455"]);
        foreach ($org->getAttributes() as $value) {
            $this->assertNotSame("22233344455", (string) $value);
        }
    }

    public function test_create_is_idempotent(): void
    {
        config(["business.virtual_accounts_enabled" => true]);
        [$org, $admin] = $this->orgWithAdmin([
            "va_account_number" => "0000000001",
            "va_status" => "active",
        ]);

        // Gateway must NOT be called for an org that already has an account.
        $mock = Mockery::mock(FlutterwaveService::class);
        $mock->shouldNotReceive("createVirtualAccount");
        $this->app->instance(FlutterwaveService::class, $mock);

        $result = VirtualAccountService::create($org, $admin, "nin", "11122233344");
        $this->assertSame("0000000001", $result->va_account_number);
    }

    public function test_create_throws_when_feature_flag_off(): void
    {
        config(["business.virtual_accounts_enabled" => false]);
        [$org, $admin] = $this->orgWithAdmin();

        $this->expectException(InvalidRequestException::class);
        VirtualAccountService::create($org, $admin, "bvn", "22233344455");
    }

    /* ── Endpoint guards ────────────────────────────────────────────────── */

    public function test_endpoint_is_flag_gated(): void
    {
        config(["business.virtual_accounts_enabled" => false]);
        [, $admin] = $this->orgWithAdmin();
        Sanctum::actingAs($admin);

        $this->postJson("/api/v2/business/organization/virtual-account", [
            "id_type" => "bvn", "id_number" => "22233344455", "consent" => true,
        ])->assertStatus(400);
    }

    public function test_endpoint_requires_an_admin(): void
    {
        config(["business.virtual_accounts_enabled" => true]);
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

        $this->postJson("/api/v2/business/organization/virtual-account", [
            "id_type" => "bvn", "id_number" => "22233344455", "consent" => true,
        ])->assertForbidden();
    }

    public function test_endpoint_rejects_bad_id_and_missing_consent(): void
    {
        config(["business.virtual_accounts_enabled" => true]);
        [, $admin] = $this->orgWithAdmin();
        Sanctum::actingAs($admin);

        // Unknown id type.
        $this->postJson("/api/v2/business/organization/virtual-account", [
            "id_type" => "passport", "id_number" => "22233344455", "consent" => true,
        ])->assertStatus(422);

        // Not 11 digits.
        $this->postJson("/api/v2/business/organization/virtual-account", [
            "id_type" => "bvn", "id_number" => "123", "consent" => true,
        ])->assertStatus(422);

        // Consent not given.
        $this->postJson("/api/v2/business/organization/virtual-account", [
            "id_type" => "bvn", "id_number" => "22233344455", "consent" => false,
        ])->assertStatus(422);
    }

    /* ── Reconciliation ─────────────────────────────────────────────────── */

    public function test_resolve_matches_by_account_number(): void
    {
        [$org] = $this->orgWithAdmin(["va_account_number" => "5551234567"]);
        $this->orgWithAdmin(["va_account_number" => "9990000000"]); // decoy

        $found = VirtualAccountService::resolveForTransfer(["account_number" => "5551234567"]);
        $this->assertNotNull($found);
        $this->assertSame($org->id, $found->id);

        $this->assertNull(VirtualAccountService::resolveForTransfer(["account_number" => "0000000000"]));
        $this->assertNull(VirtualAccountService::resolveForTransfer([])); // no keys -> no match
    }

    public function test_apply_pays_oldest_first_and_carries_overpay_as_credit(): void
    {
        [$org] = $this->orgWithAdmin();
        $this->dueInvoice($org, 300000, "INV-A", now()->subMonths(2));
        $this->dueInvoice($org, 300000, "INV-B", now()->subMonth());

        // Enough to clear both + ₦5,000 over.
        $result = VirtualAccountService::applyToInvoices($org, 605000);

        $this->assertEqualsCanonicalizing(["INV-A", "INV-B"], $result["paid"]);
        $this->assertSame(OrganizationInvoice::STATUS_PAID, OrganizationInvoice::where("reference", "INV-A")->first()->status);
        $this->assertSame(OrganizationInvoice::STATUS_PAID, OrganizationInvoice::where("reference", "INV-B")->first()->status);
        $this->assertEquals(5000, (float) $org->refresh()->credit_balance);
    }

    public function test_apply_stops_at_first_uncovered_invoice_fifo(): void
    {
        [$org] = $this->orgWithAdmin();
        $old = $this->dueInvoice($org, 300000, "INV-OLD", now()->subMonths(2));
        $new = $this->dueInvoice($org, 100000, "INV-NEW", now()->subMonth());

        // Enough for the newer, cheaper invoice — but NOT the older one. FIFO: pay none,
        // don't jump the queue; carry the whole amount as credit.
        $result = VirtualAccountService::applyToInvoices($org, 100000);

        $this->assertSame([], $result["paid"]);
        $this->assertSame(OrganizationInvoice::STATUS_DUE, $old->refresh()->status);
        $this->assertSame(OrganizationInvoice::STATUS_DUE, $new->refresh()->status);
        $this->assertEquals(100000, (float) $org->refresh()->credit_balance);
    }

    public function test_apply_draws_on_existing_credit_to_clear_an_invoice(): void
    {
        [$org] = $this->orgWithAdmin(["credit_balance" => 250000]);
        $inv = $this->dueInvoice($org, 300000, "INV-C", now()->subMonth());

        // ₦250k credit + ₦60k transfer clears the ₦300k invoice, ₦10k left as credit.
        $result = VirtualAccountService::applyToInvoices($org, 60000);

        $this->assertSame(["INV-C"], $result["paid"]);
        $this->assertSame(OrganizationInvoice::STATUS_PAID, $inv->refresh()->status);
        $this->assertEquals(10000, (float) $org->refresh()->credit_balance);
    }

    public function test_record_transfer_is_idempotent_on_reference(): void
    {
        [$org] = $this->orgWithAdmin();
        $inv = $this->dueInvoice($org, 300000, "INV-D", now()->subMonth());

        $first = VirtualAccountService::recordTransfer($org, 300000, "flw-ref-777");
        $this->assertTrue($first["applied"]);
        $this->assertSame(OrganizationInvoice::STATUS_PAID, $inv->refresh()->status);

        // A webhook retry with the same reference must not double-apply.
        $second = VirtualAccountService::recordTransfer($org, 300000, "flw-ref-777");
        $this->assertFalse($second["applied"]);
        $this->assertSame(1, \App\Models\Payment::where("reference", "flw-ref-777")->count());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
