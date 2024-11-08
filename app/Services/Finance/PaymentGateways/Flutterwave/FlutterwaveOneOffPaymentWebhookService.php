<?php

namespace App\Services\Finance\PaymentGateways\Flutterwave;

use App\Constants\Finance\Payment\PaymentConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\Payment\FlutterwaveException;
use App\Models\Payment;
use App\Models\Promotion;
use App\Models\User;
use App\Notifications\Finance\Payment\AdminNewPaymentNotification;
use App\Notifications\Finance\Payment\NewPaymentNotification;
use App\Services\Finance\Payment\PaymentIntentService;
use App\Services\Group\GroupService;
use App\Services\Post\PostService;
use App\Services\Promotion\PromotionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class FlutterwaveOneOffPaymentWebhookService
{
    public array $payload, $metadata, $transaction_data;
    public User $user;
    public Payment $payment;
    public Promotion $promotion;
    public $payment_intent_service;

    public function __construct()
    {
        $this->payment_intent_service = new PaymentIntentService;
    }

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
            return $this->payment->refresh();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function parsePayload()
    {
        $payload = $this->payload["data"];

        if (empty($payload)) {
            throw new FlutterwaveException("Payment data not set!");
        }

        $this->metadata = $payload["meta"] ?? $this->payload["meta_data"] ?? $payload["data"]["meta_data"] ?? null;

        $this->user = $this->setUser($payload);
        $this->payment = $this->setPayment($payload);
    }

    public function setUser($payload)
    {
        if (isset($payload["customer"])) {
            $user = User::where("email", $payload["customer"]["email"])->first();
        }

        if (empty($user)) {
            throw new FlutterwaveException("We could not verify the owner of this payment.");
        }

        return $user;
    }

    public function setPayment($payload)
    {
        if (isset($payload["tx_ref"])) {
            $payment = $this->payment_intent_service
                ->payment_service->getByReference($payload["tx_ref"]);
        }

        if (empty($payment)) {
            throw new FlutterwaveException("We could not verify this payment.");
        }

        return $payment;
    }

    private function actionHandler()
    {
        $activity = $this->metadata["activity"];

        if (in_array($activity, [PaymentConstants::PAYMENT_FOR_PROMOTION])) {
            $this->handlePaymentForPromotion();
        }

        if (isset($this->metadata["payload"])) {
            $this->handlePayloadAction($this->metadata);
        }

    }

    public function handlePayloadAction($metadata)
    {
        $payload = decrypt($metadata["payload"]);
        
        $payload_data = $payload["data"] ?? null;
        $payload_type = $payload["type"] ?? null;

        if ($payload_type == "Post") {
            $post = (new PostService)->create($payload_data);
            $this->promotion->update([
                "post_id" => $post->id
            ]);
        }

        if ($payload_type == "Group") {
            $group = (new GroupService)->create($payload_data);
            $this->promotion->update([
                "group_id" => $group->id
            ]);
        }
    }

    public function handlePaymentForPromotion()
    {
        DB::beginTransaction();
        try {
            $metadata = $this->metadata;

            $this->promotion = $promotion = PromotionService::getById($metadata["promotion_id"]);

            if (empty($promotion)) {
                throw new InvalidRequestException("We could not verify your promotion request.");
            }

            if (in_array($this->payment->status, [StatusConstants::FAILED, StatusConstants::COMPLETED])) {
                throw new InvalidRequestException("Payment has already been verified.");
            }

            $this->payment->update([
                "status" => StatusConstants::COMPLETED
            ]);

            $promotion->update([
                "payment_id" => $this->payment->id,
            ]);

            Notification::send($this->user, new NewPaymentNotification($this->payment));
            if (!empty(sudo())) {
                Notification::send(sudo(), new AdminNewPaymentNotification($this->payment));
            }

            DB::commit();
            return $promotion;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }
}
