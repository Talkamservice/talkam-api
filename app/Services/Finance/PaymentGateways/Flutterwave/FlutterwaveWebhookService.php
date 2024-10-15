<?php

namespace App\Services\Finance\PaymentGateways\Flutterwave;

use App\Exceptions\General\InvalidRequestException;
use App\Services\System\ExceptionService;
use Exception;
use Illuminate\Support\Facades\DB;

class FlutterwaveWebhookService
{
    public array $payload;

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

    public function determineWebhookDestination($payload) {
        
    }

    public function handleOneOffPayments($payload)
    {
        return (new FlutterwaveOneOffPaymentWebhookService)
            ->setPayload($payload)
            ->handle();
    }

    public function handleSubscriptionPayments($payload)
    {
        return (new FlutterwaveSubscriptionPaymentWebhookService)
            ->setPayload($payload)
            ->handle();
    }
}
