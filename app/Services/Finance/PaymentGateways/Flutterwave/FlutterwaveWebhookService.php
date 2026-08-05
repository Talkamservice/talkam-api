<?php

namespace App\Services\Finance\PaymentGateways\Flutterwave;

use App\Constants\Finance\Payment\PaymentConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Services\System\ExceptionService;
use Exception;
use Illuminate\Support\Facades\DB;

class FlutterwaveWebhookService
{
    public array $payload;
    public $flutterwave_service;

    public function __construct()
    {
        $this->flutterwave_service = new FlutterwaveService;
    }

    public function setPayload(array $value)
    {
        $this->payload = $value;
        return $this;
    }

    public function handle()
    {
        DB::beginTransaction();
        try {
            $payload = $this->payload;

            if (!in_array($payload["event"] ?? null, ["charge.completed", "subscription.cancelled", "transfer.completed"])) {
                throw new InvalidRequestException("The event is unregistered");
            }

            if (in_array($payload["event"], ["charge.completed"])) {
                $this->determineWebhookDestination($payload);
            }

            if (in_array($payload["event"], ["subscription.cancelled"])) {
                $this->handleSubscriptionPayments($payload);
            }

            // Additive v2 branch (therapist payouts) — v1 events untouched.
            if (in_array($payload["event"], ["transfer.completed"])) {
                (new \App\Services\Therapist\PayoutHandlerService)
                    ->setPayload($payload)
                    ->handle();
            }

            DB::commit();
        } catch (Exception $th) {
            DB::rollBack();
            ExceptionService::logAndBroadcast($th);
            throw $th;
        }
    }

    public function determineWebhookDestination($payload)
    {
        try {
            $transaction = $this->flutterwave_service
                ->verifyTransaction($payload["data"]["id"]);

            if (!isset($transaction["data"]["meta"])) {
                throw new InvalidRequestException("We could not ascertain the purpose of this webhook");
            }

            if (!in_array($transaction["data"]["status"], ["successful"])) {
                throw new InvalidRequestException($transaction["message"] ?? null);
            }

            $meta = $transaction["data"]["meta"] ?? $payload["meta_data"];
            $activity = $meta["activity"];

            if (in_array($activity, [
                PaymentConstants::PAYMENT_FOR_PROMOTION,
                PaymentConstants::PAYMENT_FOR_BUSINESS_BUNDLE,
                PaymentConstants::PAYMENT_FOR_CARD_SETUP,
            ])) {
                return $this->handleOneOffPayments($payload, $transaction);
            } else if (in_array($activity, [PaymentConstants::PAYMENT_FOR_SUBSCRIPTION])) {
                return $this->handleSubscriptionPayments($payload);
            }

            throw new InvalidRequestException("Hook purpose not found");
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function handleOneOffPayments($payload, $flutterwave_transaction)
    {
        return (new FlutterwaveOneOffPaymentWebhookService)
            ->setPayload($payload)
            ->setTransactionData($flutterwave_transaction)
            ->handle();
    }

    public function handleSubscriptionPayments($payload)
    {
        return (new FlutterwaveSubscriptionPaymentWebhookService)
            ->setPayload($payload)
            ->handle();
    }
}
