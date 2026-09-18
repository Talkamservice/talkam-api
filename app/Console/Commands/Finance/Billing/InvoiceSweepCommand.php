<?php

namespace App\Console\Commands\Finance\Billing;

use App\Services\Business\OrganizationBillingRunService;
use Illuminate\Console\Command;

/**
 * Daily net-terms sweep (web §08 Phase 2a): remind on invoices due soon and
 * flag overdue ones. Each notice is one-shot per invoice.
 */
class InvoiceSweepCommand extends Command
{
    protected $signature = 'billing:invoice-sweep';

    protected $description = 'Send due-soon reminders and overdue notices for unpaid B2B invoices';

    public function handle(): int
    {
        $result = OrganizationBillingRunService::sweep();

        $this->info(sprintf(
            'Invoice sweep: %d reminder(s), %d overdue notice(s).',
            $result['reminders'],
            $result['overdue']
        ));

        return self::SUCCESS;
    }
}
