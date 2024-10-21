<?php

namespace App\Services\Finance\Payment;

use App\Constants\Finance\Payment\PaymentConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\MethodsHelper;
use App\Models\Payment;
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
}
