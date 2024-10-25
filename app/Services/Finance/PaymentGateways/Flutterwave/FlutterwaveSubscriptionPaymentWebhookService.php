<?php

namespace App\Services\Finance\PaymentGateways\Flutterwave;

use App\Constants\General\StatusConstants;
use App\Exceptions\Payment\FlutterwaveException;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\Finance\Subscription\SubscriptionDisabledNotification;
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
        $this->subscription = $this->setSubcription($payload);
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

    public function setSubcription($payload)
    {
        if (isset($payload["id"])) {
            $subscription = Subscription::where("flutterwave_subscription_id", $payload["id"])->first();
        }

        if (empty($subscription)) {
            throw new FlutterwaveException("We could not verify this subscription.");
        }

        return $subscription;
    }

    private function actionHandler()
    {
        //Handle new, recurring and failed subscription;
        
        // if (in_array($this->payload["data"]["object"]["status"], ["unpaid", "past_due"])) {
        //     return $this->disableUserSubscription();
        // }
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
