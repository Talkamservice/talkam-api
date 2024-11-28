<?php

namespace App\Services\Finance\PaymentGateways\Flutterwave;

use App\Constants\General\StatusConstants;
use App\Exceptions\Payment\FlutterwaveException;
use App\Models\PlanDuration;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\Finance\Subscription\NewSubscriptionNotification;
use App\Notifications\Finance\Subscription\SubscriptionDisabledNotification;
use App\Services\Finance\Subscription\SubscriptionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class FlutterwaveSubscriptionPaymentWebhookService
{
    public array $payload, $transaction_data;
    public User $user;
    public Subscription $subscription;

    public function setPayload(array $value)
    {
        $this->payload = $value;
        return $this;
    }

    public function setTransactionData(array $transaction_data)
    {
        $this->transaction_data = $transaction_data;
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
        $subscription = Subscription::where("plan_duration_id", $this->payload["meta_data"]["plan_duration_id"])
            ->where("user_id", $this->user->id)->first();

        if (empty($subscription)) {
            $this->initiateUserSubscription();
        } else {
            $this->handleRecurringSubscription($subscription);
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

    public function handleRecurringSubscription($subscription) {
    }

    public function disableUserSubscription()
    {
        $subscription = $this->subscription;
        $subscription->update([
            "status" => StatusConstants::INACTIVE
        ]);

        Notification::send($this->user, new SubscriptionDisabledNotification($subscription));
    }
}
