<?php

namespace App\Services\Finance\Subscription;

use App\Constants\Finance\Payment\PaymentConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\Finance\SubscriptionException;
use App\Models\PlanDuration;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Finance\PaymentGateways\Stripe\StripeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SubscriptionService
{
    public static function getById($id): Subscription
    {
        $subscription = Subscription::find($id);
        if (empty($subscription)) {
            throw new SubscriptionException("Subscription not found");
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

    public function initiate(array $data)
    {
        DB::beginTransaction();
        try {
            $data = self::validate($data);
            $plan_duration = PlanDuration::find($data["plan_duration_id"]);
            self::checkForSubscription(auth()->user(), $plan_duration);
            $response = $this->createPaymentIntent($plan_duration);
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
            throw new SubscriptionException("You are already subscribed to this plan");
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

    public function createPaymentIntent($plan_duration)
    {
        $user = auth()->user();

        if (empty($user->stripe_customer_id)) {
            $response = (new StripeService)->setCustomerData([
                "name" => $user->name,
                "email" => $user->email,
            ])->createCustomer();

            if (!empty($response)) {
                $user->update([
                    "stripe_customer_id" => $response["id"]
                ]);
            }
        }

        $payment_intent_response = (new StripeService)->setPaymentIntentData([
            'customer' => $user->stripe_customer_id,
            "amount" => ($plan_duration->price * 100),
            "currency" => "aed",
            "receipt_email" => $user->email,
            "setup_future_usage" => "on_session",
            "metadata" => [
                "plan_duration_id" => $plan_duration->id,
                "stripe_customer_id" => $user->stripe_customer_id,
                "activity" => PaymentConstants::PAYMENT_FOR_SUBSCRIPTION
            ]
        ])->createPaymentIntent();

        if (empty($payment_intent_response)) {
            throw new SubscriptionException("Unable to initiate payment");
        }

        return [
            "payment_intent" => $payment_intent_response["id"],
            "client_secret" => $payment_intent_response["client_secret"],
            "amount" => $plan_duration->price,
        ];
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
        (new StripeService)->cancelSubscription($subscription->stripe_subscription_id);

        $subscription->update([
            "status" => StatusConstants::CANCELLED
        ]);

        return $subscription->refresh();
    }
}
