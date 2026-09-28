<?php

namespace App\Console\Commands;

use App\Services\Therapist\SessionLifecycleService;
use Illuminate\Console\Command;

class SweepSessionCompletionsCommand extends Command
{
    protected $signature = 'bookings:sweep-session-completions';

    protected $description = 'Auto-complete ended sessions and mark no-shows (therapist no-show refunds in full)';

    public function handle(): int
    {
        (new SessionLifecycleService)->sweep();

        $this->info("Session completion sweep finished.");

        return self::SUCCESS;
    }
}
