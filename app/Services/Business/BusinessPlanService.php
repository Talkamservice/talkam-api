<?php

namespace App\Services\Business;

use App\Models\BusinessPlan;
use Illuminate\Support\Collection;

/**
 * The B2B plan catalogue (Wellbeing Lite/Core/Plus) — now real, platform-
 * admin-editable rows (business_plans/business_plan_tiers/
 * business_plan_features) instead of the old config('business.plans')
 * array. Read side only; platform-admin CRUD lives in
 * PlatformAdmin\PlatformBusinessPlanService.
 *
 * Deliberately NOT the same source as OrganizationPricingService::tier()
 * (config('business.seat_tiers')) — that's the actual billing-computation
 * table, untouched here. This service only backs what a business admin sees
 * on the Billing page's plan-comparison cards.
 */
class BusinessPlanService
{
    /** Ordered, with tiers/features eager-loaded — what the billing page renders. */
    public static function catalogue(): Collection
    {
        return BusinessPlan::with(["tiers", "features"])
            ->orderBy("sort_order")
            ->get();
    }

    public static function find(int $id): ?BusinessPlan
    {
        return BusinessPlan::with(["tiers", "features"])->find($id);
    }

    /** Which named plan a seat count falls into — "lite" if nothing matches. */
    public static function keyForSeats(int $seats): string
    {
        $plan = BusinessPlan::orderBy("sort_order")
            ->get()
            ->first(function (BusinessPlan $plan) use ($seats) {
                $above = $seats >= $plan->min_seats;
                $below = $plan->max_seats === null || $seats <= $plan->max_seats;

                return $above && $below;
            });

        return $plan?->key ?? "lite";
    }

    public static function findByKey(string $key): ?BusinessPlan
    {
        return BusinessPlan::with(["tiers", "features"])->where("key", $key)->first();
    }
}
