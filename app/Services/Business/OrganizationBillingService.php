<?php

namespace App\Services\Business;

use App\Models\Organization;
use App\Models\OrganizationInvoice;
use App\Models\TherapySession;
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
                "label" => "Employee Seats · {$quote["seats"]} × " . self::naira($quote["rates"]["employee_seat"]),
                "value" => self::naira($quote["employee_seats_monthly"]),
            ];
        }

        if ($quote["therapist_access"]) {
            $lines[] = [
                "label" => "Therapist Network Access · {$quote["seats"]} × " . self::naira($quote["rates"]["therapist_access"]),
                "value" => self::naira($quote["therapist_access_monthly"]),
            ];
        }

        if ($quote["bundle_sessions"] > 0) {
            $lines[] = [
                "label" => "Session Bundle · {$quote["bundle_sessions"]} × " . self::naira($quote["rates"]["session"]),
                "value" => self::naira($quote["session_bundle_monthly"]),
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
                "tone" => $invoice->status === OrganizationInvoice::STATUS_PAID ? "green" : "gold",
            ])
            ->all();
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

        return $invoice->due_at ? "Due " . $invoice->due_at->format("M j") : "Due";
    }

    private static function nextReset(): Carbon
    {
        return now()->startOfMonth()->addMonth();
    }
}
