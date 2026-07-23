<?php

namespace App\Services\Therapist;

use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\MethodsHelper;
use App\Models\Payout;
use App\Models\Therapist;
use App\Models\TherapistPayoutAccount;
use App\Models\TherapistWalletTransaction;
use App\Services\Finance\PaymentGateways\Flutterwave\FlutterwaveService;
use Illuminate\Support\Facades\DB;

class PayoutService
{
    /**
     * On-demand withdrawal: always the full available balance (config
     * minimum applies). Debits the ledger and initiates the transfer.
     */
    public function withdraw(Therapist $therapist, string $initiated_by = 'manual'): Payout
    {
        $balance = EarningsLedgerService::balance($therapist);
        $minimum = (float) config('therapist.payout.minimum');

        if ($balance <= 0 || $balance < $minimum) {
            throw new InvalidRequestException("Your available balance is below the payout minimum.");
        }

        $account = TherapistPayoutAccount::where('user_id', $therapist->user_id)->first();
        if (empty($account)) {
            throw new InvalidRequestException("No verified payout account on file.");
        }

        $reference = "PAYOUT-" . strtoupper(MethodsHelper::getRandomToken(10));

        DB::beginTransaction();
        try {
            $payout = Payout::create([
                'therapist_id' => $therapist->id,
                'payout_account_id' => $account->id,
                'amount' => $balance,
                'provider' => 'flutterwave',
                'provider_ref' => $reference,
                'status' => 'pending',
                'initiated_by' => $initiated_by,
            ]);

            TherapistWalletTransaction::create([
                'therapist_id' => $therapist->id,
                'type' => EarningsLedgerService::TYPE_DEBIT,
                'payout_id' => $payout->id,
                'amount' => $balance,
                'reference' => $reference,
            ]);

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }

        // No BVN data — bank code, account number, amount, reference only.
        app(FlutterwaveService::class)->initiateTransfer([
            'account_bank' => $account->bank_code,
            'account_number' => $account->account_number,
            'amount' => $balance,
            'currency' => config('therapist.session_rate.currency'),
            'reference' => $reference,
            'narration' => 'TalkAM therapist payout',
        ]);

        return $payout->refresh();
    }

    public static function getOwned(Therapist $therapist, $payout_id): Payout
    {
        $payout = Payout::where('id', $payout_id)
            ->where('therapist_id', $therapist->id)
            ->first();

        if (empty($payout)) {
            throw new ModelNotFoundException("Payout not found");
        }

        return $payout;
    }
}
