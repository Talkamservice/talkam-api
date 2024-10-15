<?php

namespace App\Services\Finance\PaymentGateways\Stripe;

use App\Constants\Finance\Payment\PaymentConstants;
use App\Exceptions\Finance\StripeException;
use App\Models\PlanDuration;
use App\Models\User;
use App\Notifications\Finance\Subscription\AdminNewSubscriptionNotification;
use App\Notifications\Finance\Subscription\NewSubscriptionNotification;
use App\Services\Finance\Subscription\SubscriptionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class StripePaymentWebhookService
{
    public array $payload;
    public User $user;
    public $metadata;

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
            throw new StripeException("Payment intent data not set!");
        }

        $this->user = $this->setUser($payload);
    }

    public function setUser($payload)
    {
        $this->metadata = $metadata = $payload["metadata"];

        if (isset($metadata["stripe_customer_id"])) {
            $user = User::where("stripe_customer_id", $metadata["stripe_customer_id"])->first();
        }

        if (empty($user)) {
            $user = User::where("email", $payload["receipt_email"])->first();
        }

        if (empty($user)) {
            throw new StripeException("We could not verify the owner of this payment.");
        }

        return $user;
    }

    private function actionHandler()
    {
        if ($this->payload["data"]["object"]["status"] == "succeeded") {
            return $this->handleSuccess();
        }
    }

    private function handleSuccess()
    {
        if ($this->metadata["activity"] == PaymentConstants::PAYMENT_FOR_SUBSCRIPTION) {
            $this->initiateUserSubscription();
        }
    }

    public function initiateUserSubscription()
    {
        $metadata = $this->metadata;
        $plan_duration = PlanDuration::find($metadata["plan_duration_id"]);

        if (empty($plan_duration)) {
            throw new StripeException("We could not verify your plan.");
        }

        $current_subscription = SubscriptionService::currentUserSubscription($this->user, $plan_duration);

        if (!empty($current_subscription)) {
            SubscriptionService::cancel($current_subscription);
        }

        $subscription = SubscriptionService::subscribeToPlan($this->user, $plan_duration);
        $payment_method = $this->payload["data"]["object"]["charges"]["data"][0]["payment_method"] ?? $this->payload["data"]["object"]["payment_method"] ?? null;

        if (empty($payment_method)) {
            throw new StripeException("We could not verify the method of payment.");
        }

        $this->createStripeSubscription($subscription, $payment_method);
        Notification::send($this->user, new NewSubscriptionNotification($subscription));
        Notification::send(sudo(), new AdminNewSubscriptionNotification($subscription));
    }

    public function createStripeSubscription($subscription, $payment_method)
    {
        $user = $subscription->user;
        $plan_duration = $subscription->planDuration;

        $response = (new StripeService)->setSubscriptionData([
            'customer' => $user->stripe_customer_id,
            'default_payment_method' => $payment_method,
            'cancel_at_period_end' => "false",
            'items' => [
                [
                    'price' => $plan_duration->stripe_price_id,
                ],
            ],
        ])->createSubscription();


        $subscription->update([
            "stripe_subscription_id" => $response["id"] ?? $subscription->stripe_subscription_id ?? null,
            "stripe_client_secret" => $this->payload["data"]["object"]["client_secret"] ?? $subscription->stripe_client_secret ?? null
        ]);
    }
}
