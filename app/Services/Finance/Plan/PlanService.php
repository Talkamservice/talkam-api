<?php

namespace App\Services\Finance\Plan;

use App\Constants\General\StatusConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\Currency;
use App\Models\Plan;
use App\Services\Finance\PaymentGateways\Flutterwave\FlutterwaveService;
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
        $validator = Validator::make($data, [
            "name" => 'required|string',
            "description" => 'nullable|string',
            "scopes" => "nullable|array",
            "benefits" => "nullable|array",
            "status" => "required|string|" . Rule::in(StatusConstants::ACTIVE_OPTIONS),
            "price" => 'nullable|array',
            "price.*" => 'nullable|numeric|gt:-1',
            "discount" => "nullable|array",
            "discount.*" => 'nullable|numeric|gte:0',
            "frequency" => 'nullable|array',
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

            $plan->scopes()->delete();
            $scopes = self::scopeCleanUp($data["scopes"]);

            foreach ($scopes as $key => $scope) {
                (new PlanScopeService)->save([
                    "plan_id" => $plan->id,
                    "title" => $scope["title"],
                    "value" =>  $scope["value"],
                    "status" => StatusConstants::ACTIVE
                ]);
            }

            $plan_scopes = $plan->scopes;

            foreach ($plan_scopes ?? [] as $key => $scope) {
                $title = self::parseTitle($scope);
                if (!empty($title)) {
                    (new PlanBenefitService)->save([
                        "plan_id" => $plan->id,
                        "title" => $title,
                        "key" => $scope->slug,
                        "value" => "Yes",
                        "status" => StatusConstants::ACTIVE
                    ]);
                }
            }

            if (!empty($data["price"] ?? null) && !empty($data["frequency"] ?? null)) {
                (new PlanDurationService)->saveMultiple($data, $plan);
                self::createFlutterwavePlan($plan);
            }
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

            $plan->scopes()->delete();
            foreach ($data["scopes"] as $key => $value) {
                (new PlanScopeService)->save([
                    "plan_id" => $plan->id,
                    "title" => $key,
                    "value" =>  $value,
                    "status" => StatusConstants::ACTIVE
                ]);
            }

            $plan->benefits()->delete();
            $plan_scopes = $plan->scopes;

            foreach ($plan_scopes ?? [] as $key => $scope) {
                $title = self::parseTitle($scope);
                if (!empty($title)) {
                    (new PlanBenefitService)->save([
                        "plan_id" => $plan->id,
                        "title" => $title,
                        "key" => $scope->slug,
                        "value" => "Yes",
                        "status" => StatusConstants::ACTIVE
                    ]);
                }
            }

            // Fetch the existing durations before deletion
            if (!empty($data["price"] ?? null) && !empty($data["frequency"] ?? null)) {
                $existingDurations = $plan->durations()->pluck('flutterwave_plan_id', 'id')->toArray();
                $plan->durations()->delete();
                (new PlanDurationService)->saveMultiple($data, $plan, $existingDurations);
                self::updateFlutterwavePlan($plan, $existingDurations);
            }
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
            $currency = Currency::where('short_name', 'USD')->first();

            $transaction_data = [
                "amount" => $amount,
                "name" => $plan->name,
                "interval" => strtolower($duration->frequency),
                "duration" => $duration->duration,
                "currency" => $currency ? $currency->short_name : 'USD',
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

    public static function updateFlutterwavePlan($plan, $existingDurations)
    {
        foreach ($existingDurations as $duration) {
            // Check if the duration has a flutterwave_plan_id
            if (!empty($duration->flutterwave_plan_id)) {
                // Fetch plan details from Flutterwave using flutterwave_plan_id
                $existingPlan = (new FlutterwaveService)->getPlan($duration->flutterwave_plan_id);

                if ($existingPlan && $existingPlan['data']['id'] == $duration->flutterwave_plan_id) {
                    // If the plan exists and fields like amount or interval have changed
                    if ($plan->isDirty(['amount', 'interval', 'duration'])) {
                        // Create a new Flutterwave plan and cancel the old one
                        self::createFlutterwavePlan($plan);
                        self::cancelFlutterwavePlan($duration);
                    } else {
                        // Otherwise, update allowed fields
                        $transaction_data = [
                            "name" => $plan->name,
                            "status" => $plan->status,
                        ];
                        $response = (new FlutterwaveService)->setPlanData($transaction_data)
                            ->updatePlan($duration->flutterwave_plan_id);
                        if (!empty($response)) {
                            // Update flutterwave_plan_id if necessary
                            $duration->update([
                                'flutterwave_plan_id' => $response["data"]['id']
                            ]);
                        }
                        return $response;
                    }
                }
            } else {
                // Handle cases where flutterwave_plan_id is missing
                self::createFlutterwavePlan($plan);
            }
        }
    }

    public static function scopeCleanUp($plan_scopes)
    {
        $formatted_scopes = [];

        foreach ($plan_scopes as $key => $value) {
            $formatted_scopes[$key] = [
                "title" => $key,
                "value" => $value
            ];
        }

        return $formatted_scopes;
    }

    public static function parseTitle($scope)
    {
        $messages = [
            // 'content_creation_access' => 'Access to most community features, including posting, commenting, and voting',
            // 'ad_free_experience' => 'Enjoy ad free experience',

            // 'character_restriction' => fn($value) => (is_numeric($value) && $value > 0) ?
            //     "Enjoy up to {$value} characters when posting" : "Unlimited character when posting",

            // 'anonymous_content' => fn($value) => (is_numeric($value) && $value > 0) ?
            //     "Enjoy up to {$value} anonymous postings" : "Enjoy advanced privacy controls, including anonymous browsing within the posts and comments",

            'total_public_group_creation' => fn($value) => is_numeric($value) ?
                ($value > 0 ? "Enjoy creation of up to {$value} public groups" : "No public group creation available") : '',

            'total_private_group_creation' => fn($value) => (is_numeric($value) && $value > 0) ?
                "Enjoy creation of up to {$value} private groups" : "Access to create an unlimited number of private groups",

            'total_scheduled_post_creation' => fn($value) => (is_numeric($value) && $value > 0) ?
                "Enjoy creation of up to {$value} scheduled posts" : "Access to create an unlimited number of scheduled posts"
        ];

        if (array_key_exists($scope->title, $messages)) {
            $message = $messages[$scope->title];
            // If the message is a closure, it means it requires $scope->value
            $response = is_callable($message) ? $message($scope->value) : $message;
            return $response;
        }

        return null;
    }


    public static function cancelFlutterwavePlan($plan)
    {
        $durations = $plan->durations;
        foreach ($durations as $duration) {
            $response = (new FlutterwaveService)->cancelPlan($duration->flutterwave_plan_id);
            return $response;
        }
    }

    public static function getFlutterwavePlan($plan)
    {
        $durations = $plan->durations;
        foreach ($durations as $duration) {
            $response = (new FlutterwaveService)
                ->getPlan($duration->flutterwave_plan_id);

            return $response;
        }
    }
}
