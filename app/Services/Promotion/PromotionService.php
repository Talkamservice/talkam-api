<?php

namespace App\Services\Promotion;

use App\Constants\ActivityLog\ActivitiesConstants;
use App\Constants\ActivityLog\ActivityLogConstants;
use App\Constants\Finance\Currency\CurrencyConstants;
use App\Constants\Finance\Payment\PaymentConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\MethodsHelper;
use App\Models\Promotion;
use App\Services\ActivityLog\ActivityLogService;
use App\Services\Finance\Payment\PaymentIntentService;
use App\Services\Finance\PaymentGateways\Flutterwave\FlutterwaveService;
use App\Services\Group\GroupService;
use App\Services\Post\PostService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PromotionService
{
    public $user;
    public $flutterwave_service;
    public $payment_intent_service;
    public $additional_data;

    public function __construct()
    {
        $this->flutterwave_service = new FlutterwaveService;
        $this->payment_intent_service = new PaymentIntentService;
    }

    public static function getById($id): Promotion
    {
        $promotion = Promotion::find($id);
        if (empty($promotion)) {
            throw new ModelNotFoundException("Promotion not found");
        }
        return $promotion;
    }

    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    public static function validate($data, $id = null)
    {
        $validator = Validator::make($data, [
            "post_id" => "bail|nullable|exists:posts,id",
            "group_id" => "bail|nullable|exists:groups,id",
            "state_id" => "bail|nullable|exists:states,id",
            "country_id" => "bail|nullable|exists:countries,id",
            "min_age" => "bail|required|numeric",
            "max_age" => "bail|required|numeric",
            "gender" => "bail|nullable|string",
            "daily_budget" => "bail|nullable|numeric",
            "duration" => "bail|nullable|numeric",
            "status" => "bail|nullable|string|" . Rule::in(StatusConstants::ACTIVE_OPTIONS),
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        return $validator->validated();
    }

    public function submit(array $data)
    {
        try {
            $promotion = $this->create($data);
            $this->initiatePayment($promotion);
        } catch (\Throwable $th) {
            throw $th;
        }
    }


    public function update(array $data, $id)
    {
        try {
            $data = self::validate($data);
            $promotion = $this->getById($id);

            if (in_array($promotion->status, [StatusConstants::COMPLETED, StatusConstants::FAILED])) {
                $status = strtolower($promotion->status);
                throw new InvalidRequestException("You cannot proceed with this action because the ad has been marked as {$status}");
            }

            $promotion->update([
                "status" => $data["status"]
            ]);
            
            $promotion->refresh();
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function initiatePayment(array $data)
    {
        DB::beginTransaction();
        try {
            $promotion = $this->create($data);

            if (($data["payload"]["type"] ?? null) == "Post") {
                $payload_data = (new PostService)->validate($data["payload"]["data"]);
            }

            if (($data["payload"]["type"] ?? null) == "Group") {
                $payload_data = (new GroupService)->validate($data["payload"]["data"]);
            }

            if (empty($data["post_id"] ?? null) && empty($data["group_id"] ?? null) && empty($data["payload"] ?? null)) {
                throw new InvalidRequestException("You cannot proceed with this promotion. Kindly select a post or group to proceed");
            }

            $payment = $this->payment_intent_service->setUser($promotion->user)
                ->setAmount($promotion->cost)
                ->setCurrency(CurrencyConstants::DOLLAR_CURRENCY_SHORT_NAME)
                ->setAdditionalData([
                    "type" => PaymentConstants::DEBIT,
                    "status" => StatusConstants::PENDING,
                    "description" => "Payment for promotion of content",
                    "activity" => PaymentConstants::PAYMENT_FOR_PROMOTION,
                    "metadata" => [
                        "amount" => $promotion->cost,
                        "email" => $promotion->user->email,
                        "promotion_id" => $promotion->id,
                        "activity" => PaymentConstants::PAYMENT_FOR_PROMOTION,
                        "payload" => encrypt([
                            "type" => $data["payload"]["type"] ?? null,
                            "data" => $payload_data ?? null
                        ]),
                    ]
                ]);

            $payment = $payment->initiate();
            DB::commit();
            return $payment;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function create(array $data)
    {
        DB::beginTransaction();
        try {
            $data = self::validate($data);
            $user = $this->user = auth()->user();

            $data["cost"] = $data["daily_budget"] * $data["duration"];
            $promotion = Promotion::create(array_merge($data, [
                "user_id" => $user->id,
                "uuid" => MethodsHelper::getRandomToken(10),
            ]));

            (new ActivityLogService)
                ->setEvent("created")
                ->setTitle("Promotion Created")
                ->setDescription("{$promotion?->user?->getName()} has initiated a promotion request")
                ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
                ->setActivity(ActivitiesConstants::PROMOTION_CREATED)
                ->setModel(Promotion::class, $promotion->id)
                ->setAdmin(auth()->user()?->id)
                ->setData([
                    "promotion" => $promotion->refresh()->toArray(),
                ])
                ->setUrl(request()->fullUrl())
                ->log();

            DB::commit();
            return $promotion;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public static function delete($promotion_id)
    {
        DB::beginTransaction();
        try {
            $promotion = self::getById($promotion_id);
            $deleted_promotion = $promotion;
            $promotion->delete();

            (new ActivityLogService)
                ->setEvent("deleted")
                ->setTitle("Promotion Deleted")
                ->setDescription("{$promotion?->user?->getName()} deleted a promotion")
                ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
                ->setActivity(ActivitiesConstants::PROMOTION_DELETED)
                ->setModel(Promotion::class, $promotion->id)
                ->setAdmin(auth()->user()?->id)
                ->setData([
                    "Category" =>  $deleted_promotion->toArray()
                ])
                ->setUrl(request()->fullUrl())
                ->log();
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }


    public static function list(array $data = [])
    {
        $promotions = Promotion::with(["user"]);

        if (!empty($key = $data["search"] ?? null)) {
            $promotions = $promotions->where("name", "LIKE", "%$key%");
        }

        if (!empty($key = $data["status"] ?? null)) {
            $promotions = $promotions->where("status", $key);
        }else {
            $promotions = $promotions->whereIn("status", [StatusConstants::ACTIVE, StatusConstants::PENDING]);
        }

        if (!empty($key = $data["post_id"] ?? null)) {
            $promotions = $promotions->where("post_id", $key);
        }

        if (!empty($key = $data["group_id"] ?? null)) {
            $promotions = $promotions->where("group_id", $key);
        }

        return $promotions;
    }
}
