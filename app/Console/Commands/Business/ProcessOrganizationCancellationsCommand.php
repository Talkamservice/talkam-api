<?php

namespace App\Console\Commands\Business;

use App\Services\Business\OrganizationLifecycleService;
use Illuminate\Console\Command;

/** Daily sweep: lands subscription cancellations whose billing-period cutoff has passed (web §03 Settings → Danger Zone). */
class ProcessOrganizationCancellationsCommand extends Command
{
    protected $signature = 'organizations:process-cancellations';

    protected $description = 'Flip organizations whose scheduled cancellation date has passed to cancelled';

    public function handle(): int
    {
        $result = OrganizationLifecycleService::processCancellations();

        $this->info(sprintf('Cancellations processed: %d organization(s).', $result['cancelled']));

        return self::SUCCESS;
    }
}
