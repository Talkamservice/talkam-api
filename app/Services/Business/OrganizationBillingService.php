<?php

namespace App\Services\Business;

use App\Constants\Finance\Payment\PaymentConstants;
use App\Constants\General\StatusConstants;
use App\Helpers\MethodsHelper;
use App\Models\Organization;
use App\Models\OrganizationInvoice;
use App\Models\Payment;
use App\Models\TherapySession;
use App\Models\User;
use App\Exceptions\General\InvalidRequestException;
use Illuminate\Support\Carbon;

/**
 * The admin Billing screen (web §07): the org's current plan, usage, invoice
 * history and the plan catalogue.
 *
 * All figures are ADMINISTRATIVE (seat counts, plan totals, bundle drawdown) —
 * the privacy rule explicitly allows these; no individual employee data is
 * touched, so cohort suppression does not apply here. Everything is derived from
 * the authenticated org, never a client-supplied id.
 */
class OrganizationBillingService
{
    private static function naira($amount): string
    {
        return "₦" . number_format((float) $amount);
    }

    /**
     * The current-plan card: line items + total + renewal, built from the org's
     * live quote so it always matches what the seats/plan screens computed.
     */
    public static function currentPlan(Organization $organization): array
    {
        $quote = OrganizationPricingService::quoteFor($organization);
        $plan = self::currentPlanKey($organization);
        $name = config("business.plans.{$plan}.name", config("business.plan.name"));

        $lines = [];

        if ($quote["seats"] > 0) {
            $lines[] = [
                "label" => "Employee Seats · {$quote["seats"]} × " . self::naira($quote["rates"]["seat"]),
                "value" => self::naira($quote["seats_monthly"]),
            ];
        }

        // Pay-as-you-go sessions are a recurring line; a prepaid bundle is a
        // one-off already purchased, so it surfaces under Usage, not here.
        if ($quote["metered_sessions"]) {
            $lines[] = [
                "label" => "Sessions · pay-as-you-go at " . self::naira($quote["rates"]["metered_session"]) . "/session",
                "value" => "Billed monthly",
            ];
        }

        return [
            "label" => strtoupper($name) . " · ACTIVE",
            "lines" => $lines,
            "total" => self::naira($quote["total_monthly"]),
            "renews" => "Renews " . self::nextReset()->format("M j, Y") . " · billed monthly",
        ];
    }

    /** Session-bundle drawdown + seat usage for the usage widgets. */
    public static function usage(Organization $organization): array
    {
        $member_ids = OrgAggregateService::memberIds($organization);

        $sessions_used = empty($member_ids) ? 0 : TherapySession::whereIn("user_id", $member_ids)
            ->where("starts_at", ">=", now()->startOfMonth())
            ->count();

        // camelCase to match the web mock's `networkStats` one-to-one.
        return [
            "seatsUsed" => $organization->seatsUsed(),
            "seatsTotal" => (int) $organization->seats_licensed,
            "sessionsUsed" => $sessions_used,
            "sessionsBundle" => (int) $organization->session_bundle_sessions,
            "nextReset" => self::nextReset()->format("j M Y"),
        ];
    }

    /**
     * The plan catalogue for the change-plan / seat / top-up modals. Keys are
     * camelCase to match the web mock's `PLANS` / option arrays one-to-one.
     */
    public static function catalogue(Organization $organization): array
    {
        $current = self::currentPlanKey($organization);

        $plans = collect(config("business.plans"))
            ->mapWithKeys(function ($plan, $key) use ($current) {
                return [$key => [
                    "key" => $plan["key"],
                    "name" => $plan["name"],
                    "seatRange" => $plan["seat_range"],
                    "minSeats" => $plan["min_seats"],
                    "maxSeats" => $plan["max_seats"],
                    "defaultSeats" => $plan["default_seats"],
                    "custom" => $plan["custom"],
                    "tiers" => $plan["tiers"],
                    "features" => $plan["features"],
                    "isCurrent" => $key === $current,
                ]];
            })
            ->all();

        return [
            "plans" => $plans,
            "seatTiers" => config("business.seat_tiers"),
            "topUpOptions" => config("business.bundle_options"),
            "seatPackOptions" => config("business.seat_pack_options"),
        ];
    }

    /** The org's invoice history, newest first — tenant-scoped. */
    public static function invoices(Organization $organization): array
    {
        return OrganizationInvoice::where("organization_id", $organization->id)
            ->orderByDesc("period_start")
            ->get()
            ->map(fn (OrganizationInvoice $invoice) => [
                "id" => $invoice->reference,
                "period" => $invoice->period_start->format("M j") . " – " . $invoice->period_end->format("M j, Y"),
                "seats" => $invoice->seats,
                "amount" => self::naira($invoice->amount),
                "status" => self::statusLabel($invoice),
                "tone" => $invoice->status === OrganizationInvoice::STATUS_PAID
                    ? "green"
                    : ($invoice->isOverdue() ? "red" : "gold"),
            ])
            ->all();
    }

    /**
     * Create a pending payment for the up-front session-bundle charge and return
     * the config the web Flutterwave inline modal needs. Returns amount 0 when
     * there is nothing to charge now (no bundle) — the caller then just continues
     * the onboarding without opening a checkout.
     */
    public static function bundleCheckout(Organization $organization, User $user): array
    {
        $currency = config("business.currency");

        // Pay-as-you-go (postpay) is settled at month-end, never charged at signup.
        if ((string) $organization->payment_timing === "postpay") {
            return [
                "reference" => null,
                "amount" => 0,
                "currency" => $currency,
                "customer" => null,
                "meta" => null,
            ];
        }

        // The signup charge is the FIRST MONTH (seats) + the prepaid session bundle.
        // Reuse the same quote the onboarding screen displays so the two agree.
        $quote = OrganizationPricingService::quoteFor($organization);
        $seats_charge = (int) $quote["seats_monthly"];
        $bundle_charge = (int) $quote["bundle_total"];
        $bundle_sessions = (int) $quote["bundle_sessions"];
        $amount = $seats_charge + $bundle_charge;

        if ($amount <= 0) {
            return [
                "reference" => null,
                "amount" => 0,
                "currency" => $currency,
                "customer" => null,
                "meta" => null,
            ];
        }

        $reference = "TK-BUNDLE-" . strtoupper(MethodsHelper::getRandomToken(10));
        
        $meta = [
            "activity" => PaymentConstants::PAYMENT_FOR_BUSINESS_BUNDLE,
            "organization_id" => $organization->id,
            "bundle_sessions" => $bundle_sessions,
            "seats_charge" => $seats_charge,
            "bundle_charge" => $bundle_charge,
        ];

        Payment::create([
            "user_id" => $user->id,
            "currency" => $currency,
            "amount" => $amount,
            "reference" => $reference,
            "activity" => PaymentConstants::PAYMENT_FOR_BUSINESS_BUNDLE,
            "description" => "TalkAM for Business — first month ({$quote["seats"]} seats) + session bundle ({$bundle_sessions} sessions)",
            "type" => PaymentConstants::DEBIT,
            "metadata" => $meta,
            "status" => StatusConstants::PENDING,
        ]);

        return [
            "reference" => $reference,
            "amount" => $amount,
            "currency" => $currency,
            "customer" => [
                // A B2B charge: the paying entity is the company. The admin's email
                // stays the contact (they complete checkout and get the receipt).
                "email" => $user->email,
                "name" => $organization->name,
            ],
            "meta" => $meta,
        ];
    }

    /**
     * Fulfil a completed bundle payment — called from the Flutterwave webhook.
     * Marks the payment complete and records a paid invoice for the charge.
     * Idempotent: a repeat callback is a no-op.
     */
    public static function fulfilBundlePayment(Payment $payment): void
    {
        if ($payment->status === StatusConstants::COMPLETED) {
            return;
        }

        $organization_id = $payment->metadata["organization_id"] ?? null;
        $organization = $organization_id ? Organization::find($organization_id) : null;

        if (empty($organization)) {
            throw new InvalidRequestException("We could not match this payment to a company.");
        }

        $payment->update(["status" => StatusConstants::COMPLETED]);

        OrganizationInvoice::updateOrCreate(
            ["reference" => $payment->reference],
            [
                "organization_id" => $organization->id,
                "period_start" => now()->startOfMonth(),
                "period_end" => now()->endOfMonth(),
                "seats" => (int) $organization->seats_licensed,
                "amount" => $payment->amount,
                "status" => OrganizationInvoice::STATUS_PAID,
                "issued_at" => now(),
            ]
        );
    }

    /** Which catalogue plan the org's seat count falls into. */
    public static function currentPlanKey(Organization $organization): string
    {
        $seats = (int) $organization->seats_licensed;

        foreach (config("business.plans") as $key => $plan) {
            $above = $seats >= ($plan["min_seats"] ?? 0);
            $below = ($plan["max_seats"] ?? null) === null || $seats <= $plan["max_seats"];
            if ($above && $below) {
                return $key;
            }
        }

        return "lite";
    }

    private static function statusLabel(OrganizationInvoice $invoice): string
    {
        if ($invoice->status === OrganizationInvoice::STATUS_PAID) {
            return "Paid";
        }

        if ($invoice->isOverdue()) {
            return "Overdue";
        }

        return $invoice->due_at ? "Due " . $invoice->due_at->format("M j") : "Due";
    }

    private static function nextReset(): Carbon
    {
        return now()->startOfMonth()->addMonth();
    }
}
