<?php

namespace App\Console\Commands;

use App\Models\Therapist;
use App\Services\Therapist\EarningsLedgerService;
use App\Services\Therapist\PayoutService;
use Illuminate\Console\Command;

class WeeklyPayoutSweepCommand extends Command
{
    protected $signature = 'payouts:weekly-sweep {--force : Ignore the configured payout day}';

    protected $description = 'Weekly auto-sweep: pay out any remaining balance for every eligible therapist (config-driven day)';

    public function handle(): int
    {
        $payout_day = strtolower(config('therapist.payout_day'));

        if (!$this->option('force') && strtolower(now()->englishDayOfWeek) != $payout_day) {
            $this->info("Not the configured payout day ($payout_day); nothing to do.");
            return self::SUCCESS;
        }

        $minimum = (float) config('therapist.payout.minimum');
        $swept = 0;

        foreach (Therapist::whereHas('user.payoutAccount')->get() as $therapist) {
            if (EarningsLedgerService::balance($therapist) < $minimum) {
                continue;
            }

            try {
                (new PayoutService)->withdraw($therapist, 'auto');
                $swept++;
            } catch (\Throwable $th) {
                logger("Weekly payout sweep failed for therapist {$therapist->id}", [
                    "error" => $th->getMessage(),
                ]);
            }
        }

        $this->info("Swept $swept payout(s).");

        return self::SUCCESS;
    }
}
