<?php

namespace App\Services\Finance\Subscription;

use App\Constants\Finance\Payment\PaymentConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\PlanDuration;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Finance\Payment\PaymentIntentService;
use App\Services\Finance\PaymentGateways\Flutterwave\FlutterwaveService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SubscriptionService
{
    public $user;
    public $flutterwave_service;
    public $payment_intent_service;

    public function __construct()
    {
        $this->flutterwave_service = new FlutterwaveService;
        $this->payment_intent_service = new PaymentIntentService;
    }

    public static function getById($id): Subscription
    {
        $subscription = Subscription::find($id);
        if (empty($subscription)) {
            throw new ModelNotFoundException("Subscription not found");
        }
        return $subscription;
    }

    public static function validate(array $data, $id = null): array
    {
        $validator = Validator::make($data, [
            "plan_duration_id" => "required|exists:plan_durations,id",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    public function initiate(array $data)
    {
        DB::beginTransaction();
        try {
            $data = self::validate($data);
            $plan_duration = PlanDuration::find($data["plan_duration_id"]);
            self::checkForSubscription($this->user, $plan_duration);
            $response = $this->setUser($this->user)->initiatePayment($plan_duration);
            DB::commit();
            return $response;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public static function checkForSubscription($model, $plan_duration)
    {
        $currentSubscription = self::currentUserSubscription($model, $plan_duration);
        if (!empty($currentSubscription)) {
            throw new InvalidRequestException("You are already subscribed to this plan");
        }
    }

    public static function currentUserSubscription(User $user, PlanDuration $plan_duration)
    {
        return Subscription::where("user_id", $user->id)
            ->where("plan_duration_id", $plan_duration->id)
            ->where("expires_at", ">", now())
            ->whereHas("plan")
            ->with("plan")
            ->where("status", StatusConstants::ACTIVE)
            ->orderby("expires_at", "desc")
            ->first();
    }


    public static function list()
    {
        $subscriptions = Subscription::status()->latest();
        return $subscriptions;
    }


    public static function subscribeToPlan($user, $plan_duration)
    {
        $subscription =  Subscription::create([
            "user_id" => $user->id,
            "plan_id" => $plan_duration->plan_id,
            "plan_duration_id" => $plan_duration->id,
            "price" => $plan_duration->price,
            "paid_on" => now(),
            "expires_at" => now()->addDays($plan_duration->duration),
            "status" => StatusConstants::ACTIVE
        ]);

        return $subscription;
    }

    public static function cancel($subscription)
    {
        (new FlutterwaveService)->cancelSubscription($subscription->stripe_subscription_id);

        $subscription->update([
            "status" => StatusConstants::CANCELLED
        ]);

        return $subscription->refresh();
    }

    public function initiatePayment($plan_duration)
    {
        DB::beginTransaction();
        try {
            $payment = $this->payment_intent_service->setUser($this->user)
                ->setAmount($plan_duration->price)
                ->setCurrency($plan_duration->plan->currency->name)
                ->setAdditionalData([
                    "type" => PaymentConstants::DEBIT,
                    "status" => StatusConstants::PENDING,
                    "description" => "Payment for subscription",
                    "activity" => PaymentConstants::PAYMENT_FOR_SUBSCRIPTION,
                    "metadata" => [
                        "amount" => $plan_duration->price,
                        "plan_duration_id" => $plan_duration->id,
                        "email" => $this->user->email,
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
}
