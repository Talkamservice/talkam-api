<?php

namespace App\Services\Business;

use App\Constants\Business\OrganizationConstants;
use App\Models\Organization;
use App\Models\OrganizationInvoice;
use App\Notifications\Business\OrganizationInvoiceNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * The recurring B2B billing run (web §08 Phase 2a).
 *
 * Seats are invoiced monthly in ARREARS: once a company's first employee
 * activates, each active-employee seat is billed at the volume-tier rate the
 * company locked in. HR-admin seats are included (never billed). Net terms give
 * the payer a configurable window; a daily sweep reminds before due and flags
 * overdue.
 *
 * SCOPE NOTE: this bills SEATS only. Pay-as-you-go SESSION metering is
 * deliberately NOT here — it depends on the (not-yet-built) B2B session-coverage
 * layer (org-covered booking + bundle drawdown). Metering member sessions today
 * would double-bill, since employees currently pay the therapist directly. See
 * planning-docs/web-api/08-billing-redesign.md.
 */
class OrganizationBillingRunService
{
    /**
     * Generate invoices for every eligible org for a billing period. Idempotent:
     * re-running a period never duplicates an invoice.
     */
    public static function run(Carbon $period_start, Carbon $period_end): array
    {
        $orgs = Organization::where("status", OrganizationConstants::STATUS_ACTIVE)
            ->whereNotNull("verified_at")
            ->get();

        $created = 0;
        $total = 0.0;

        foreach ($orgs as $org) {
            try {
                $invoice = self::generateInvoice($org, $period_start, $period_end);

                if ($invoice && $invoice->wasRecentlyCreated) {
                    $created++;
                    $total += (float) $invoice->amount;
                }
            } catch (\Throwable $e) {
                logger("Billing run failed for org {$org->id}", ["error" => $e->getMessage()]);
            }
        }

        return ["orgs" => $orgs->count(), "invoiced" => $created, "total" => $total];
    }

    /**
     * One invoice for one org for one period. Returns null when there is nothing
     * to bill (no active employees yet). Idempotent on the period reference — a
     * sent invoice is never regenerated or overwritten.
     */
    public static function generateInvoice(
        Organization $organization,
        Carbon $period_start,
        Carbon $period_end
    ): ?OrganizationInvoice {
        // Prepay bills the committed (licensed) seats from day one; postpay bills
        // only active (onboarded) employee seats — so a postpay org with nobody
        // onboarded yet is billed nothing until an employee activates.
        $seats = OrganizationPricingService::billableSeats($organization);

        if ($seats < 1) {
            return null;
        }

        $rate = (int) OrganizationPricingService::tier((int) $organization->seats_licensed)["price"];
        $amount = $seats * $rate;

        $reference = "INV-" . $period_start->format("Ym") . "-" . $organization->id;

        $existing = OrganizationInvoice::where("reference", $reference)->first();
        if ($existing) {
            return $existing;
        }

        $net_days = (int) config("business.billing.net_terms_days");

        $invoice = OrganizationInvoice::create([
            "organization_id" => $organization->id,
            "reference" => $reference,
            "period_start" => $period_start->toDateString(),
            "period_end" => $period_end->toDateString(),
            "seats" => $seats,
            "amount" => $amount,
            "status" => OrganizationInvoice::STATUS_DUE,
            "issued_at" => now(),
            "due_at" => now()->addDays($net_days),
        ]);

        self::notifyAdmins($organization, new OrganizationInvoiceNotification($invoice, "issued"));

        return $invoice;
    }

    /**
     * Daily sweep: remind on invoices due soon, flag overdue ones. Both are
     * one-shot (guarded by reminded_at / overdue_notified_at).
     */
    public static function sweep(): array
    {
        $lead = (int) config("business.billing.reminder_lead_days");
        $reminders = 0;
        $overdue = 0;

        $due_soon = OrganizationInvoice::where("status", OrganizationInvoice::STATUS_DUE)
            ->whereNull("reminded_at")
            ->whereNotNull("due_at")
            ->where("due_at", ">", now())
            ->where("due_at", "<=", now()->addDays($lead))
            ->with("organization")
            ->get();

        foreach ($due_soon as $invoice) {
            self::notifyAdmins($invoice->organization, new OrganizationInvoiceNotification($invoice, "reminder"));
            $invoice->update(["reminded_at" => now()]);
            $reminders++;
        }

        $past_due = OrganizationInvoice::where("status", OrganizationInvoice::STATUS_DUE)
            ->whereNull("overdue_notified_at")
            ->whereNotNull("due_at")
            ->where("due_at", "<", now())
            ->with("organization")
            ->get();

        foreach ($past_due as $invoice) {
            self::notifyAdmins($invoice->organization, new OrganizationInvoiceNotification($invoice, "overdue"));
            $invoice->update(["overdue_notified_at" => now()]);
            $overdue++;
        }

        return ["reminders" => $reminders, "overdue" => $overdue];
    }

    /**
     * Reconcile a net-terms invoice as settled (offline bank transfer). Idempotent.
     * On settlement, releases any held therapist credits for that org's postpay
     * sessions in the invoice period (web §09) — a no-op until coverage is live.
     */
    public static function markPaid(OrganizationInvoice $invoice): OrganizationInvoice
    {
        if ($invoice->status !== OrganizationInvoice::STATUS_PAID) {
            $invoice->update([
                "status" => OrganizationInvoice::STATUS_PAID,
                "paid_at" => now(),
            ]);

            \App\Services\Therapist\EarningsLedgerService::releaseHeldCredits(
                $invoice->organization_id,
                $invoice->period_start,
                $invoice->period_end
            );
        }

        return $invoice->refresh();
    }

    /**
     * Notify the org's active admins (HR) of an invoice event — issued, a
     * due-soon reminder, or overdue. Each admin's own "Invoice notifications"
     * toggle (web §03 Settings) is honoured; an admin who never touched the
     * setting defaults to opted-in. No-op when nobody ends up subscribed.
     */
    private static function notifyAdmins(Organization $organization, $notification): void
    {
        $subscribed = AdminNotificationGateService::subscribedAdminsForOrg($organization, "invoice_notifications");

        if ($subscribed->isEmpty()) {
            return;
        }

        Notification::send($subscribed, $notification);
    }
}
