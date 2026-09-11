<?php

namespace App\Constants\Finance\Payment;

class PaymentConstants
{
    const DEBIT = "Debit";
    const CREDIT = "Credit";

    const FLUTTERWAVE = "Flutterwave";
    const PAYMENT_FOR_SUBSCRIPTION = "PAYMENT_FOR_SUBSCRIPTION";
    const PAYMENT_FOR_PROMOTION = "PAYMENT_FOR_PROMOTION";
    const PAYMENT_FOR_SESSION = "PAYMENT_FOR_SESSION";
    // B2B onboarding card checkout — the session bundle charged up front (web §07).
    const PAYMENT_FOR_BUSINESS_BUNDLE = "PAYMENT_FOR_BUSINESS_BUNDLE";
    const PAYMENT_FOR_CARD_SETUP = "PAYMENT_FOR_CARD_SETUP";
    // B2B bank transfer received into a dedicated virtual account (web §11).
    const PAYMENT_FOR_BUSINESS_TRANSFER = "PAYMENT_FOR_BUSINESS_TRANSFER";
}
