<?php

namespace App\Services\Finance\Payment;

use App\Constants\Finance\Payment\PaymentConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\MethodsHelper;
use App\Models\Payment;
use App\Services\Finance\PaymentGateways\Flutterwave\FlutterwaveOneOffPaymentWebhookService;
use App\Services\Finance\PaymentGateways\Flutterwave\FlutterwaveService;
use App\Services\Finance\PaymentGateways\Flutterwave\FlutterwaveSubscriptionPaymentWebhookService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public static function validate($data)
    {
        $validator = Validator::make($data, [
            "user_id" => "bail|nullable|exists:users,id",
            "currency" => "bail|required",
            "amount" => "bail|required|numeric|gt:0",
            "fees" => "bail|nullable|numeric|gt:-1",
            "description" => "bail|nullable|string",
            "narration" => "bail|nullable|string",
            "activity" => "bail|nullable|string",
            "reference" => "bail|nullable|string",
            "metadata" => "bail|nullable|array",
            "type" => "bail|required|string|" . Rule::in([
                PaymentConstants::CREDIT,
                PaymentConstants::DEBIT
            ]),
            "status" => "bail|nullable|string|" . Rule::in([
                StatusConstants::COMPLETED,
                StatusConstants::PENDING,
                StatusConstants::PROCESSING,
                StatusConstants::REFUNDED,
                StatusConstants::FAILED,
                StatusConstants::ROLLBACK,
                StatusConstants::DECLINED
            ]),
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public static function create($data): Payment
    {
        $data = self::validate($data);
        $data["reference"] = $data["reference"] ?? self::generateReferenceNo();
        $payment = Payment::create($data);
        return $payment;
    }

    public static function generateReferenceNo($length = 4)
    {
        $key = "RF_" . date("YmdHis") . "_"  . MethodsHelper::getRandomToken($length, true);
        return $key;
    }

    public static function getByReference($reference): Payment
    {
        $payment = Payment::where("reference", $reference)->first();

        if (empty($payment)) {
            throw new ModelNotFoundException(
                "Payment not found",
            );
        }
        return $payment;
    }

    public static function markAs(string $reference, $payload, string $status)
    {
        $payment = Payment::where("reference", $reference)
            ->first();

        $payment->update([
            "status" => $status
        ]);
    }

    public static function getById($key, $column = "id"): Payment
    {
        $payment = Payment::where($column, $key)->first();

        if (empty($payment)) {
            throw new ModelNotFoundException(
                "Payment not found",
            );
        }

        return $payment;
    }

    public function callback(array $data = [])
    {
        try {
            $validator = Validator::make($data, [
                "reference" => "bail|required|string",
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $data = $validator->validated();

            // Container-resolved (behavior-identical) so tests can bind a mock.
            $transaction = $this->verifyWithRetry($data["reference"]);

            if (!isset($transaction["data"]["meta"])) {
                throw new InvalidRequestException("We could not ascertain the purpose of this payment");
            }

            if (!in_array($transaction["data"]["status"], ["successful"])) {
                throw new InvalidRequestException($transaction["message"] ?? null);
            }

            $meta = $transaction["data"]["meta"];
            $activity = $meta["activity"];

            if (in_array($activity, [PaymentConstants::PAYMENT_FOR_PROMOTION])) {
                return $this->handleOneOffPayments($transaction);
            } else if (in_array($activity, [PaymentConstants::PAYMENT_FOR_SUBSCRIPTION])) {
                return $this->handleSubscriptionPayments($transaction, $transaction);
            } else if (in_array($activity, [PaymentConstants::PAYMENT_FOR_SESSION])) {
                // Additive v2 branch (therapist session bookings) — v1
                // activities above are untouched.
                return (new \App\Services\Therapist\SessionPaymentHandlerService)
                    ->setPayload($transaction)
                    ->handle();
            }
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    /**
     * A callback that fires right after checkout can beat Flutterwave's own
     * tx_ref search index — verifyTransactionByReference briefly comes back
     * with no matching transaction (no "meta") for a payment that actually
     * just succeeded. Retries only that specific "not found yet" shape;
     * a transaction that's genuinely found but failed/pending returns
     * immediately, same as before.
     */
    private function verifyWithRetry(string $reference): array
    {
        $attempts = max(1, (int) config("services.flutterwave.verifyRetries", 3));
        $delayMs = (int) config("services.flutterwave.verifyRetryDelayMs", 1000);

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $transaction = app(FlutterwaveService::class)->verifyTransactionByReference($reference);

            if (isset($transaction["data"]["meta"]) || $attempt === $attempts) {
                return $transaction;
            }

            usleep($delayMs * 1000);
        }

        return $transaction;
    }

    public function handleOneOffPayments($payload, $flutterwave_transaction = null)
    {
        return (new FlutterwaveOneOffPaymentWebhookService)
            ->setPayload($payload)
            ->handle();
    }

    public function handleSubscriptionPayments($payload, $flutterwave_transaction = null)
    {
        return (new FlutterwaveSubscriptionPaymentWebhookService)
            ->setPayload($payload)
            ->handle();
    }

    public function list(array $data = [])
    {
        $payments = Payment::query();

        if (!empty($key = $data["search"] ?? null)) {
            $payments = $payments->where("name", "LIKE", "%$key%");
        }

        if (!empty($key = $data["status"] ?? null)) {
            $payments = $payments->where("status", $key);
        }

        return $payments;
    }
}
