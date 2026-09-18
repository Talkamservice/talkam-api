<?php

namespace App\Console\Commands\Business;

use App\Services\Business\OrganizationLifecycleService;
use Illuminate\Console\Command;

/** Daily sweep: purges organizations whose 30-day deletion grace period has elapsed (web §03 Settings → Danger Zone). */
class PurgeScheduledOrganizationDeletionsCommand extends Command
{
    protected $signature = 'organizations:purge-scheduled-deletions';

    protected $description = 'Deactivate members and soft-delete organizations past their scheduled deletion date';

    public function handle(): int
    {
        $result = OrganizationLifecycleService::purgeScheduledDeletions();

        $this->info(sprintf('Organizations purged: %d.', $result['purged']));

        return self::SUCCESS;
    }
}
