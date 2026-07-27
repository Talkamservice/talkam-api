<?php

namespace Tests\Feature\V2\Business;

use App\Constants\Business\OrganizationConstants as OC;
use App\Models\Organization;
use App\Models\OrganizationInvoice;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Notifications\Business\OrganizationInvoiceNotification;
use App\Services\Business\OrganizationBillingRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The monthly seat-billing run (web §08 Phase 2a): generate net-terms invoices
 * for active-employee seats, remind/flag overdue, and reconcile as paid.
 */
class BillingRunTest extends TestCase
{
    use RefreshDatabase;

    private function org(array $overrides = []): array
    {
        $org = Organization::create(array_merge([
            "name" => "Meridian Health",
            "slug" => "meridian-" . uniqid(),
            "domain" => "meridian" . uniqid() . ".ng",
            "status" => OC::STATUS_ACTIVE,
            "seats_licensed" => 250, // 101–300 band → ₦6,000/seat
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

    private function addEmployees(Organization $org, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            OrganizationMember::create([
                "organization_id" => $org->id,
                "user_id" => User::factory()->create()->id,
                "role" => OC::ROLE_EMPLOYEE,
                "status" => OC::MEMBER_ACTIVE,
                "activated_at" => now(),
            ]);
        }
    }

    private function lastMonth(): array
    {
        $start = now()->subMonthNoOverflow()->startOfMonth();
        return [$start, $start->copy()->endOfMonth()];
    }

    private function invoiceFor(Organization $org, array $overrides = []): OrganizationInvoice
    {
        return OrganizationInvoice::create(array_merge([
            "organization_id" => $org->id,
            "reference" => "INV-" . strtoupper(uniqid()),
            "period_start" => now()->subMonth()->startOfMonth()->toDateString(),
            "period_end" => now()->subMonth()->endOfMonth()->toDateString(),
            "seats" => 2,
            "amount" => 12000,
            "status" => OrganizationInvoice::STATUS_DUE,
            "issued_at" => now(),
            "due_at" => now()->addDays(14),
        ], $overrides));
    }

    public function test_run_invoices_active_employee_seats_at_the_tier_rate(): void
    {
        Notification::fake();
        [$org, $admin] = $this->org();
        $this->addEmployees($org, 3);

        [$start, $end] = $this->lastMonth();
        $result = OrganizationBillingRunService::run($start, $end);

        $this->assertSame(1, $result["invoiced"]);

        $invoice = OrganizationInvoice::where("organization_id", $org->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame(3, $invoice->seats);                       // active EMPLOYEES only (admin excluded)
        $this->assertEquals(18000, (float) $invoice->amount);        // 3 × ₦6,000
        $this->assertSame(OrganizationInvoice::STATUS_DUE, $invoice->status);
        $this->assertSame(14, $invoice->issued_at->diffInDays($invoice->due_at)); // net-14

        Notification::assertSentTo($admin, OrganizationInvoiceNotification::class);
    }

    public function test_run_skips_an_org_with_no_active_employees(): void
    {
        Notification::fake();
        [$org] = $this->org(); // admin only

        [$start, $end] = $this->lastMonth();
        OrganizationBillingRunService::run($start, $end);

        $this->assertSame(0, OrganizationInvoice::where("organization_id", $org->id)->count());
    }

    public function test_run_is_idempotent_for_a_period(): void
    {
        Notification::fake();
        [$org] = $this->org();
        $this->addEmployees($org, 2);
        [$start, $end] = $this->lastMonth();

        OrganizationBillingRunService::run($start, $end);
        OrganizationBillingRunService::run($start, $end);

        $this->assertSame(1, OrganizationInvoice::where("organization_id", $org->id)->count());
    }

    public function test_sweep_reminds_due_soon_and_flags_overdue_once_each(): void
    {
        Notification::fake();
        [$org] = $this->org();

        $dueSoon = $this->invoiceFor($org, ["reference" => "INV-SOON", "due_at" => now()->addDays(2)]);
        $overdue = $this->invoiceFor($org, ["reference" => "INV-LATE", "due_at" => now()->subDays(2)]);
        $paidLate = $this->invoiceFor($org, [
            "reference" => "INV-PAID",
            "due_at" => now()->subDays(5),
            "status" => OrganizationInvoice::STATUS_PAID,
        ]);

        $result = OrganizationBillingRunService::sweep();

        $this->assertSame(1, $result["reminders"]);
        $this->assertSame(1, $result["overdue"]);
        $this->assertNotNull($dueSoon->refresh()->reminded_at);
        $this->assertNotNull($overdue->refresh()->overdue_notified_at);
        $this->assertNull($paidLate->refresh()->overdue_notified_at); // paid invoices are never chased

        // One-shot: a second sweep does nothing.
        $again = OrganizationBillingRunService::sweep();
        $this->assertSame(0, $again["reminders"]);
        $this->assertSame(0, $again["overdue"]);
    }

    public function test_mark_paid_endpoint_is_admin_gated_and_tenant_scoped(): void
    {
        [$org, $admin] = $this->org();
        $invoice = $this->invoiceFor($org, ["reference" => "INV-PAYME"]);

        // An employee cannot mark invoices paid.
        $employee = User::factory()->create();
        OrganizationMember::create([
            "organization_id" => $org->id,
            "user_id" => $employee->id,
            "role" => OC::ROLE_EMPLOYEE,
            "status" => OC::MEMBER_ACTIVE,
            "activated_at" => now(),
        ]);
        Sanctum::actingAs($employee);
        $this->postJson("/api/v2/business/billing/invoices/INV-PAYME/mark-paid")->assertForbidden();
        $this->assertSame(OrganizationInvoice::STATUS_DUE, $invoice->refresh()->status);

        // The admin can.
        Sanctum::actingAs($admin);
        $this->postJson("/api/v2/business/billing/invoices/INV-PAYME/mark-paid")->assertOk();
        $invoice->refresh();
        $this->assertSame(OrganizationInvoice::STATUS_PAID, $invoice->status);
        $this->assertNotNull($invoice->paid_at);

        // Another company's admin cannot reach it (tenant scoping → 404).
        [, $otherAdmin] = $this->org();
        Sanctum::actingAs($otherAdmin);
        $this->postJson("/api/v2/business/billing/invoices/INV-PAYME/mark-paid")->assertStatus(404);
    }
}
