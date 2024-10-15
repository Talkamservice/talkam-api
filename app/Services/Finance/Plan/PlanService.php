<?php

namespace App\Services\Finance\Plan;

use App\Constants\Finance\Plan\PlanConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\Plan;
use App\Services\Finance\PaymentGateways\Stripe\StripeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlanService
{
    public static function getById($id): Plan
    {
        $plan = Plan::find($id);
        if (empty($plan)) {
            throw new ModelNotFoundException("Plan not found");
        }
        return $plan;
    }

    public static function validate(array $data, $id = null): array
    {
        dd($data);
        $validator = Validator::make($data, [
            "name" => 'required|string',
            "description" => 'nullable|string',
            "benefits" => "nullable|array",
            "status" => "required|string|" . Rule::in(StatusConstants::ACTIVE_OPTIONS),
            // "frequency" => 'required|array',
            // "frequency.*" => 'string|' . Rule::in(PlanConstants::FREQUENCY_OPTIONS),
            // "feature_cards" => 'nullable|array',
            "price" => 'required|array',
            "price.*" => 'numeric|gt:-1',
            "discount" => "required|array",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public static function create(array $data)
    {
        DB::beginTransaction();
        try {
            $data = self::validate($data);

            $plan =  Plan::create([
                "name" => $data["name"],
                "description" => $data["description"],
                "status" => $data["status"],
                "feature_cards" => array_keys($data["feature_cards"] ?? []),
            ]);

            foreach ($data["benefits"] ?? [] as $key => $value) {
                (new PlanBenefitService)->save([
                    "plan_id" => $plan->id,
                    "title" => PlanConstants::PLAN_FEATURES[$key],
                    "key" => $key,
                    "value" => "Yes",
                    "status" => StatusConstants::ACTIVE
                ]);
            }

            (new PlanDurationService)->saveMultiple($data, $plan);
            self::createStripePrices($plan);

            DB::commit();
            return $plan;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public static function update(array $data, $id)
    {
        DB::beginTransaction();
        try {
            $data = self::validate($data, $id);
            $plan = self::getById($id);

            $plan->update([
                "name" => $data["name"],
                "description" => $data["description"],
                "status" => $data["status"],
                // "feature_cards" => array_keys($data["feature_cards"] ?? []),
            ]);

            $plan->benefits()->delete();
            foreach ($data["benefits"] as $key => $value) {
                (new PlanBenefitService)->save([
                    "plan_id" => $plan->id,
                    "title" => PlanConstants::PLAN_FEATURES[$key],
                    "key" => $key,
                    "value" => "Yes",
                    "status" => StatusConstants::ACTIVE
                ]);
            }

            foreach ($plan->durations as $key => $duration) {
                $duration->subscriptions()->delete();
            }

            $plan->durations()->delete();
            (new PlanDurationService)->saveMultiple($data, $plan);
            self::updateStripePrices($plan);

            DB::commit();
            return $plan;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function save(array $data, $id = null)
    {
        if (!empty($id)) {
            $plan = $this->update($data, $id);
        } else {
            $plan = $this->create($data);
        }

        return $plan;
    }

    public static function list()
    {
        $plans = Plan::status()->latest();
        return $plans;
    }

    public static function listByCurrentPlan($plan_id)
    {
        $plan = Plan::status()
            ->orderByRaw("id = ? DESC", [$plan_id])
            ->latest()
            ->orderBy('id', 'DESC');

        return $plan;
    }

    public static function createStripePrices($plan)
    {
        $durations = $plan->durations;

        foreach ($durations as $key => $duration) {
            $response = (new StripeService)->setPriceData([
                'currency' => 'aed',
                'unit_amount' => floatval(self::parsePlanPrice($duration)),
                'recurring' => ['interval' => strtolower(substr($duration->frequency, 0, -2))],
                'product_data' => ['name' => $plan->name],
            ])->createPrice();

            if (!empty($response)) {
                $duration->update([
                    "stripe_price_id" => $response["id"]
                ]);
            }
        }
    }

    public static function updateStripePrices($plan)
    {
        $durations = $plan->durations()->whereNotNull("stripe_price_id")->get();

        foreach ($durations as $key => $duration) {
            (new StripeService)->setPriceData([
                'currency' => 'aed',
                'unit_amount' => floatval(self::parsePlanPrice($duration)),
                'recurring' => ['interval' => substr($plan->frequency, 0, -2)],
                'product_data' => ['name' => $plan->name],
            ])->updatePrice($duration->stripe_price_id);
        }
    }

    static function parsePlanPrice($duration)
    {
        $price = $duration->price;

        if ($duration->discount > 0) {
            $discount = ($duration->discount / 100) * $price;
            $final_price = $price - $discount;
        }

        return $final_price ?? $price;
    }

    public static function fetchCurrentPlan()
    {
        $user = auth()->user();
        $active_sub = $user->activeSubscription;
        if (!empty($active_sub)) {
            $plan_id = $active_sub->plan_id;
        } else {
            $free_plan = Plan::where("name", "LIKE" , "%free%")->first();
            $plan_id = $free_plan?->id;
        }

        return $plan_id ?? null;
    }
}
