<?php

namespace App\Services\Finance\Plan;

namespace App\Services\Finance\Plan;

use App\Constants\Finance\Plan\PlanConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Exceptions\Payment\FlutterwaveException;
use App\Models\Plan;
use App\Services\Finance\PaymentGateways\Flutterwave\FlutterwaveService;
use App\Services\System\ExceptionService;
use Exception;
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
        // dd($data);
        $validator = Validator::make($data, [
            "name" => 'required|string',
            "description" => 'nullable|string',
            "benefits" => "nullable|array",
            "status" => "required|string|" . Rule::in(StatusConstants::ACTIVE_OPTIONS),
            "price" => 'required|array',
            "price.*" => 'numeric|gt:-1',
            "discount" => "nullable|array",
            "discount.*" => 'nullable|numeric|gte:0',
            "frequency" => 'required|array',
            "frequency.*" => [
                'string',
                Rule::in(['Monthly', 'Yearly']), // Add lowercase options
            ],

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
            $plan = Plan::create([
                "name" => $data["name"],
                "description" => $data["description"],
                "status" => $data["status"],
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
            self::createFlutterwavePlan($plan);
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

            // foreach ($plan->durations as $key => $duration) {
            //     $duration->subscriptions()->delete();
            // }

            $plan->durations()->delete();
            (new PlanDurationService)->saveMultiple($data, $plan);
            self::updateFlutterwavePlan($plan);
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

    public function cancel($id)
    {
        $plan = $this->getById($id);
        // dd($plan)
        $plan->update(['status' => StatusConstants::CANCELLED]);
        foreach ($plan->durations as $duration) {
            $duration->update(['status' => StatusConstants::CANCELLED]);
        }
        self::cancelFlutterwavePlan($plan);
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
            $free_plan = Plan::where("name", "LIKE", "%free%")->first();
            $plan_id = $free_plan?->id;
        }

        return $plan_id ?? null;
    }

    public static function createFlutterwavePlan($plan)
    {
        $durations = $plan->durations;

        foreach ($durations as $duration) {
            $amount = floatval((new PlanService)->parsePlanPrice($duration));

            $transaction_data = [
                "amount" => $amount,
                "name" => $plan->name,
                "interval" => strtolower($duration->frequency),
                "duration" => $duration->duration,
                "currency" => $plan->currency
            ];

            $response = (new FlutterwaveService)->setPlanData($transaction_data)
                ->createPlan();

            if (!empty($response)) {
                $duration->update([
                    'flutterwave_plan_id' => $response["data"]['id']
                ]);
            }
        }
    }
    public static function updateFlutterwavePlan($plan)
    {

        $durations = $plan->durations;
        foreach ($durations as $duration) {

            // Check if the plan needs to be updated
            if ($plan->isDirty('amount') || $plan->isDirty('interval') || $plan->isDirty('duration')) {
                // Create a new plan since these fields cannot be updated
                self::createFlutterwavePlan($plan);
                // Cancel the old plan after creating a new one
                self::cancelFlutterwavePlan($plan);
            } else {
                // Proceed with updating the fields that are allowed (status, name)
                $transaction_data = [
                    "name" => $plan->name,
                    "status" => $plan->status,
                ];

                $response = (new FlutterwaveService)->setPlanData($transaction_data)
                    ->updatePlan();
                if (!empty($response)) {
                    $duration->update([
                        'flutterwave_plan_id' => $response["data"]['id']
                    ]);
                }
                return $response;
            }
        }
    }


    public static function cancelFlutterwavePlan($plan)
    {
        $durations = $plan->durations;
        foreach ($durations as $duration) {
            $response = (new FlutterwaveService)
                ->setFlutterwavePlanId($duration->flutterwave_plan_id)
                ->cancelPlan();
        return $response;

        }
    }

    public static function getFlutterwavePlan($plan)
    {
        $durations = $plan->durations;
        foreach ($durations as $duration) {
            $response = (new FlutterwaveService)
                ->setFlutterwavePlanId($duration->flutterwave_plan_id)
                ->getPlan();
            return $response;
        }
    }
}
