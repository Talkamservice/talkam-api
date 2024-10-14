<?php

namespace App\Services\Finance\PaymentGateways\Stripe;

use App\Constants\General\StatusConstants;
use App\Exceptions\Finance\StripeException;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\Finance\Subscription\SubscriptionDisabledNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class StripeSubscriptionWebhookService
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
        $payload = $this->payload["data"]["object"];

        if (empty($payload)) {
            throw new StripeException("Subscription data not set!");
        }

        $this->user = $this->setUser($payload);
        $this->subscription = $this->setSubcription($payload);
    }

    public function setUser($payload)
    {
        if (isset($payload["customer"])) {
            $user = User::where("stripe_customer_id", $payload["customer"])->first();
        }

        if (empty($user)) {
            throw new StripeException("We could not verify the owner of this subscription.");
        }

        return $user;
    }

    public function setSubcription($payload)
    {
        if (isset($payload["id"])) {
            $subscription = Subscription::where("stripe_subscription_id", $payload["id"])->first();
        }

        if (empty($subscription)) {
            throw new StripeException("We could not verify this subscription.");
        }

        return $subscription;
    }

    private function actionHandler()
    {
        if (in_array($this->payload["data"]["object"]["status"], ["unpaid", "past_due"])) {
            return $this->disableUserSubscription();
        }
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
