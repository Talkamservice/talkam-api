<?php

namespace App\Services\Therapist;

use App\Exceptions\General\InvalidRequestException;
use App\Models\Payout;
use App\Models\TherapistWalletTransaction;
use App\Notifications\Therapist\PayoutReceivedNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Transfer-event branch of the webhook dispatch (planning doc 13).
 */
class PayoutHandlerService
{
    public array $payload = [];

    public function setPayload(array $payload)
    {
        $this->payload = $payload;
        return $this;
    }

    public function handle(): ?Payout
    {
        $data = $this->payload["data"] ?? [];
        $reference = $data["reference"] ?? null;

        $payout = Payout::with(['therapist.user', 'payoutAccount'])
            ->where('provider_ref', $reference)
            ->first();

        if (empty($payout)) {
            throw new InvalidRequestException("Payout not found for transfer reference: $reference");
        }

        if ($payout->status != 'pending') {
            return $payout;
        }

        $status = strtoupper($data["status"] ?? '');

        if (in_array($status, ["SUCCESSFUL", "SUCCESS"])) {
            $payout->update([
                'status' => 'successful',
                'completed_at' => now(),
            ]);

            if (!empty($payout->therapist?->user)) {
                Notification::send($payout->therapist->user, new PayoutReceivedNotification($payout));
            }

            return $payout->refresh();
        }

        // Failure: reverse the ledger debit so the balance is restored.
        $payout->update(['status' => 'failed']);

        TherapistWalletTransaction::where('payout_id', $payout->id)
            ->where('type', EarningsLedgerService::TYPE_DEBIT)
            ->update(['status' => EarningsLedgerService::STATUS_REVERSED]);

        return $payout->refresh();
    }
}
