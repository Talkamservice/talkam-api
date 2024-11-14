<?php

namespace App\Services\Finance\PaymentGateways\Stripe;

use App\Constants\General\StatusConstants;
use App\Exceptions\Finance\StripeException;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\Finance\Subscription\AdminSubscriptionRenewalNotification;
use App\Notifications\Finance\Subscription\SubscriptionRenewalNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class StripeInvoicePaymentWebhookService
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
            throw new StripeException("Invoice data not set!");
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
        if (isset($payload["subscription"])) {
            $subscription = Subscription::where("stripe_subscription_id", $payload["subscription"])->first();
        }

        if (empty($subscription)) {
            throw new StripeException("We could not verify this subscription.");
        }

        return $subscription;
    }

    private function actionHandler()
    {
        if ($this->payload["data"]["object"]["status"] == "paid") {
            return $this->renewUserSubscription();
        }
    }

    public function renewUserSubscription()
    {
        $subscription = $this->subscription;
        $plan_duration = $subscription->planDuration;

        $subscription->update([
            "paid_on" => now(),
            "expires_at" => now()->addDays($plan_duration->duration),
            "status" => StatusConstants::ACTIVE
        ]);

        Notification::send($this->user, new SubscriptionRenewalNotification($subscription));
        Notification::send(sudo(), new AdminSubscriptionRenewalNotification($subscription));
    }
}
