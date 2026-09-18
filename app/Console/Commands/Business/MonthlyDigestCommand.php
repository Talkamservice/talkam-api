<?php

namespace App\Console\Commands\Business;

use App\Services\Business\OrganizationDigestService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * The monthly usage-digest run (web §03 Settings). Runs on the 1st for the
 * month that just ended; idempotent, so a re-run never double-sends.
 */
class MonthlyDigestCommand extends Command
{
    protected $signature = 'digest:run-monthly {--month= : Target month as YYYY-MM (defaults to last month)}';

    protected $description = 'Send the monthly usage-digest email to subscribed company admins';

    public function handle(): int
    {
        $month = $this->option('month');

        $period_start = $month
            ? Carbon::createFromFormat('Y-m', $month)->startOfMonth()
            : now()->subMonthNoOverflow()->startOfMonth();
        $period_end = $period_start->copy()->endOfMonth();

        $result = OrganizationDigestService::run($period_start, $period_end);

        $this->info(sprintf(
            'Monthly digest for %s: %d org(s), %d digest(s) sent.',
            $period_start->format('M Y'),
            $result['orgs'],
            $result['sent']
        ));

        return self::SUCCESS;
    }
}
