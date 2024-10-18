<?php

namespace App\Services\Finance\Payment;

use Illuminate\Support\Facades\DB;

class PaymentIntentService
{
    public $user;
    public $currency;
    public $amount, $fees;
    public $payment_service;
    public $reference;
    public $additional_data;

    public function __construct()
    {
        $this->user = auth()->user();
        $this->payment_service = new PaymentService;
    }

    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    public function setAmount($amount)
    {
        $this->amount = $amount;
        return $this;
    }

    public function setCurrency($currency)
    {
        $this->currency = $currency;
        return $this;
    }

    public function setPaymentReference($reference)
    {
        $this->reference = $reference;
        return $this;
    }

    public function setFees($fees)
    {
        $this->fees = $fees;
    }

    public function setAdditionalData(array $additional_data)
    {
        $this->additional_data = $additional_data;
        return $this;
    }

    public function initiate()
    {
        DB::beginTransaction();
        try {
            $payment = $this->payment_service->create([
                "user_id" => $this->user->id,
                "currency" => $this->currency,
                "amount" => $this->amount,
                "fees" => $this->fees ?? 0,
                ...$this->additional_data
            ]);
            DB::commit();
            return $payment;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function update()
    {
        DB::beginTransaction();
        try {
            $payment = $this->payment_service->getByReference($this->reference);
            $payment->update($this->additional_data);
            DB::commit();
            return $payment;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }
}
