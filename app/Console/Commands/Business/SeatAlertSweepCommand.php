<?php

namespace App\Console\Commands\Business;

use App\Services\Business\SeatAlertService;
use Illuminate\Console\Command;

/** Daily seat-limit alert sweep (web §03 Settings). */
class SeatAlertSweepCommand extends Command
{
    protected $signature = 'seats:alert-sweep';

    protected $description = 'Alert org admins whose licensed seats are running low, once per dip';

    public function handle(): int
    {
        $result = SeatAlertService::sweep();

        $this->info(sprintf(
            'Seat alert sweep: %d org(s) checked, %d alerted, %d reset.',
            $result['orgs'],
            $result['alerted'],
            $result['reset']
        ));

        return self::SUCCESS;
    }
}
