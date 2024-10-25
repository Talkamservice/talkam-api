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
            if (in_array($payload["event"] ?? null, ["charge.completed"])) {
                $this->determineWebhookDestination($payload);
            } else {
                throw new InvalidRequestException("The event is unregistered");
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

            if (!isset($transaction["data"]["meta_data"])) {
                throw new InvalidRequestException("We could not ascertain the purpose of this webhook");
            }

            if (!in_array($transaction["data"]["status"], ["successful"])) {
                throw new InvalidRequestException($transaction["message"] ?? null);
            }

            $meta = $transaction["data"]["meta_data"];
            $activity = $meta["activity"];

            if (in_array($activity, [PaymentConstants::PAYMENT_FOR_PROMOTION])) {
                $this->handleOneOffPayments($payload, $transaction);
            }

            if (in_array($activity, [PaymentConstants::PAYMENT_FOR_SUBSCRIPTION])) {
                $this->handleSubscriptionPayments($payload, $transaction);
            }
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

    public function handleSubscriptionPayments($payload, $flutterwave_transaction)
    {
        return (new FlutterwaveSubscriptionPaymentWebhookService)
            ->setPayload($payload)
            ->setTransactionData($flutterwave_transaction)
            ->handle();
    }
}
