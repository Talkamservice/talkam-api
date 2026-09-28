<?php

namespace App\Console\Commands\Finance\Billing;

use App\Services\Business\OrganizationBillingRunService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Monthly B2B seat billing (web §08 Phase 2a). Runs on the 1st for the month
 * that just ended; idempotent, so a re-run never duplicates invoices.
 */
class MonthlyBillingRunCommand extends Command
{
    protected $signature = 'billing:run-monthly {--month= : Target billing month as YYYY-MM (defaults to last month)}';

    protected $description = 'Generate monthly B2B seat invoices (arrears) for the given month';

    public function handle(): int
    {
        $month = $this->option('month');

        $period_start = $month
            ? Carbon::createFromFormat('Y-m', $month)->startOfMonth()
            : now()->subMonthNoOverflow()->startOfMonth();
        $period_end = $period_start->copy()->endOfMonth();

        $result = OrganizationBillingRunService::run($period_start, $period_end);

        $this->info(sprintf(
            'Billing run for %s: %d org(s), %d invoice(s) issued, total NGN %s.',
            $period_start->format('M Y'),
            $result['orgs'],
            $result['invoiced'],
            number_format($result['total'])
        ));

        return self::SUCCESS;
    }
}
