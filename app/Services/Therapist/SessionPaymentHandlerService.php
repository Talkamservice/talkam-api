<?php

namespace App\Services\Therapist;

use App\Constants\General\StatusConstants;
use App\Constants\Finance\Payment\PaymentConstants;
use App\Constants\Therapist\TherapistConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Models\Payment;
use App\Models\TherapySession;
use App\Notifications\Therapist\SessionBookedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * PAYMENT_FOR_SESSION branch of the payment callback dispatch.
 */
class SessionPaymentHandlerService
{
    protected $payload;

    public function setPayload($payload)
    {
        $this->payload = $payload;
        return $this;
    }

    public function handle()
    {
        $data = $this->payload["data"] ?? [];
        $reference = $data["tx_ref"] ?? $data["reference"] ?? null;

        $payment = Payment::where("reference", $reference)
            ->where("activity", PaymentConstants::PAYMENT_FOR_SESSION)
            ->first();

        if (empty($payment)) {
            throw new InvalidRequestException("Session payment not found for reference: $reference");
        }

        $session = TherapySession::find($payment->metadata["booking_id"] ?? null);
        if (empty($session)) {
            throw new InvalidRequestException("Booking not found for session payment: $reference");
        }

        DB::beginTransaction();
        try {
            $payment->update(["status" => StatusConstants::COMPLETED]);

            $session->update([
                "status" => TherapistConstants::SESSION_CONFIRMED,
                "payment_id" => $payment->id,
                "hold_expires_at" => null,
            ]);

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }

        // Opt-in tokenization (§09): keep the card for faster repeat checkout.
        if (!empty($payment->metadata["save_card"]) && !empty($data["card"])) {
            \App\Services\User\PaymentMethodService::storeFromChargeResponse($session->user, $data["card"]);
        }

        Notification::send($session->user, new SessionBookedNotification($session, "user"));
        if (!empty($session->therapist?->user)) {
            Notification::send($session->therapist->user, new SessionBookedNotification($session, "therapist"));
        }

        return $session->refresh();
    }

    /**
     * Failure path (driven by the v2 callback controller): mark the payment
     * and its booking failed so the slot frees immediately.
     */
    public static function markFailedByReference(?string $reference): void
    {
        if (empty($reference)) {
            return;
        }

        $payment = Payment::where("reference", $reference)
            ->where("activity", PaymentConstants::PAYMENT_FOR_SESSION)
            ->first();

        if (empty($payment)) {
            return;
        }

        $payment->update(["status" => StatusConstants::FAILED]);

        $session = TherapySession::find($payment->metadata["booking_id"] ?? null);
        if (!empty($session) && $session->status == TherapistConstants::SESSION_PENDING_PAYMENT) {
            $session->update([
                "status" => TherapistConstants::SESSION_FAILED,
                "hold_expires_at" => null,
            ]);
        }
    }
}
