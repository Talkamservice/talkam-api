<?php

namespace App\Services\PlatformAdmin;

use App\Exceptions\General\ModelNotFoundException;
use App\Models\BusinessPlan;
use App\Services\Business\BusinessPlanService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Platform-admin CRUD over the B2B plan catalogue (business_plans/
 * business_plan_tiers/business_plan_features) — what a business admin sees
 * as the Wellbeing Lite/Core/Plus cards on their Billing page
 * (OrganizationBillingService::catalogue(), which reads the exact same
 * table via BusinessPlanService, read-only from that side).
 *
 * update()/create() replace the tiers/features wholesale (delete then
 * recreate) rather than diffing — each plan's tier/feature list is small,
 * ordered, and never referenced by id from anywhere else, so a full
 * replace-on-save is simpler and can't drift into a partially-applied edit.
 */
class PlatformBusinessPlanService
{
    public static function list(): Collection
    {
        return BusinessPlanService::catalogue();
    }

    public static function find(int $id): BusinessPlan
    {
        $plan = BusinessPlanService::find($id);

        if (empty($plan)) {
            throw new ModelNotFoundException("Plan not found");
        }

        return $plan;
    }

    public static function create(array $data): BusinessPlan
    {
        $validated = self::validate($data);

        return DB::transaction(function () use ($validated) {
            $plan = BusinessPlan::create([
                "key" => $validated["key"],
                "name" => $validated["name"],
                "seat_range" => $validated["seat_range"],
                "min_seats" => $validated["min_seats"],
                "max_seats" => $validated["max_seats"] ?? null,
                "default_seats" => $validated["default_seats"],
                "is_custom" => $validated["is_custom"] ?? false,
                "sort_order" => $validated["sort_order"] ?? ((BusinessPlan::max("sort_order") ?? 0) + 1),
            ]);

            self::replaceTiers($plan, $validated["tiers"] ?? []);
            self::replaceFeatures($plan, $validated["features"] ?? []);

            return $plan->refresh()->load(["tiers", "features"]);
        });
    }

    public static function update(int $id, array $data): BusinessPlan
    {
        $plan = self::find($id);
        $validated = self::validate($data, $plan->id);

        return DB::transaction(function () use ($plan, $validated) {
            $plan->update([
                "key" => $validated["key"],
                "name" => $validated["name"],
                "seat_range" => $validated["seat_range"],
                "min_seats" => $validated["min_seats"],
                "max_seats" => $validated["max_seats"] ?? null,
                "default_seats" => $validated["default_seats"],
                "is_custom" => $validated["is_custom"] ?? false,
                "sort_order" => $validated["sort_order"] ?? $plan->sort_order,
            ]);

            self::replaceTiers($plan, $validated["tiers"] ?? []);
            self::replaceFeatures($plan, $validated["features"] ?? []);

            return $plan->refresh()->load(["tiers", "features"]);
        });
    }

    public static function delete(int $id): void
    {
        self::find($id)->delete();
    }

    private static function replaceTiers(BusinessPlan $plan, array $tiers): void
    {
        $plan->tiers()->delete();

        $order = 0;
        foreach ($tiers as $tier) {
            $order++;
            $plan->tiers()->create([
                "min_seats" => $tier["min"],
                "max_seats" => $tier["max"] ?? null,
                "price" => $tier["price"],
                "sort_order" => $order,
            ]);
        }
    }

    private static function replaceFeatures(BusinessPlan $plan, array $features): void
    {
        $plan->features()->delete();

        $order = 0;
        foreach ($features as $label) {
            $order++;
            $plan->features()->create([
                "label" => $label,
                "sort_order" => $order,
            ]);
        }
    }

    private static function validate(array $data, ?int $ignore_id = null): array
    {
        $validator = Validator::make($data, [
            "key" => [
                "required", "string", "max:40", "alpha_dash",
                Rule::unique("business_plans", "key")->ignore($ignore_id),
            ],
            "name" => "required|string|max:100",
            "seat_range" => "required|string|max:60",
            "min_seats" => "required|integer|min:0",
            "max_seats" => "nullable|integer|gt:min_seats",
            "default_seats" => "required|integer|min:0",
            "is_custom" => "nullable|boolean",
            "sort_order" => "nullable|integer|min:0",
            "tiers" => "nullable|array",
            "tiers.*.min" => "required_with:tiers|integer|min:0",
            "tiers.*.max" => "nullable|integer",
            "tiers.*.price" => "required_with:tiers|integer|min:0",
            "features" => "nullable|array",
            "features.*" => "string|max:150",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
