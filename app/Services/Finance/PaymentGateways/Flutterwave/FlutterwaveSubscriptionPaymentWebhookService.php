<?php

namespace App\Services\Finance\PaymentGateways\Flutterwave;

use App\Constants\General\StatusConstants;
use App\Exceptions\Payment\FlutterwaveException;
use App\Models\PlanDuration;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\Finance\Subscription\NewSubscriptionNotification;
use App\Notifications\Finance\Subscription\SubscriptionDisabledNotification;
use App\Notifications\Finance\Subscription\SubscriptionRenewalNotification;
use App\Services\Finance\Subscription\SubscriptionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class FlutterwaveSubscriptionPaymentWebhookService
{
    public array $payload;
    public User $user;
    public Subscription $subscription;

    public function setPayload(array $value)
    {
        $this->payload = $value;
        return $this;
    }

    public function handle()
    {
        DB::beginTransaction();
        try {
            $this->parsePayload();
            $this->actionHandler();
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function parsePayload()
    {
        $payload = $this->payload["data"];

        if (empty($payload)) {
            throw new FlutterwaveException("Subscription data not set!");
        }

        $this->user = $this->setUser($payload);
    }

    public function setUser($payload)
    {
        if (isset($payload["customer"])) {
            $user = User::where("email", $payload["customer"]["email"])->first();
        }

        if (empty($user)) {
            throw new FlutterwaveException("We could not verify the owner of this subscription.");
        }

        return $user;
    }


    private function actionHandler()
    {
        if ($this->payload["event"] == "subscription.cancelled") {
            $plan_duration = PlanDuration::where("flutterwave_plan_id", $this->payload["data"]["plan"]["id"])
                ->where("status", StatusConstants::ACTIVE)
                ->first();

            if ($plan_duration) {
                $subscription = Subscription::where("plan_duration_id", $plan_duration->id)
                    ->where("user_id", $this->user->id)
                    ->first();

                if (!empty($subscription)) {
                    $this->disableUserSubscription($subscription);
                }
            }
        } else {
            if (isset($this->payload["meta_data"]["plan_duration_id"])) {
                $subscription = Subscription::where("plan_duration_id", $this->payload["meta_data"]["plan_duration_id"])
                    ->where("user_id", $this->user->id)->first();
            } else {
                $subscription = $this->user->subscriptions;
            }

            if (empty($subscription)) {
                $this->initiateUserSubscription();
            } else {
                $this->handleRecurringSubscription($subscription);
            }
        }
    }


    public function initiateUserSubscription()
    {
        $metadata = $this->payload["meta_data"];

        $plan_duration = PlanDuration::find($metadata["plan_duration_id"]);

        if (empty($plan_duration)) {
            throw new FlutterwaveException("We could not verify your plan.");
        }

        $current_subscription = SubscriptionService::currentUserSubscription($this->user, $plan_duration);

        if (!empty($current_subscription)) {
            SubscriptionService::cancel($current_subscription);
        }

        $subscription = SubscriptionService::subscribeToPlan($this->user, $plan_duration);

        if ($this->user?->should_display_ads != 1) {
            $this->user?->update([
                "should_display_ads" => 0
            ]);
        }

        Notification::send($this->user, new NewSubscriptionNotification($subscription));
        // Notification::send(sudo(), new AdminNewSubscriptionNotification($subscription));
    }

    public function handleRecurringSubscription($subscription)
    {
        if ($this->user?->should_display_ads != 1) {
            $this->user?->update([
                "should_display_ads" => 0
            ]);
        }

        Subscription::where("user_id", $subscription->user_id)
            ->update([
                "status" => StatusConstants::INACTIVE
            ]);

        $subscription->update([
            "status" => StatusConstants::ACTIVE,
            "paid_on" => now(),
            "expires_at" => carbon()->parse($subscription->expires_at)->addDays($subscription->duration),
        ]);

        Notification::send($this->user, new SubscriptionRenewalNotification($subscription));
    }

    public function disableUserSubscription($subscription)
    {
        $subscription->update([
            "status" => StatusConstants::INACTIVE
        ]);

        Notification::send($this->user, new SubscriptionDisabledNotification($subscription));
    }
}
