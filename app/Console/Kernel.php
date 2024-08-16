<?php

namespace App\Console;

use App\Console\Commands\Post\PostCommand;
use App\Console\Commands\Post\TrendingPostCommand;
use App\Console\Commands\TestCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{

    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        TestCommand::class,
        PostCommand::class,
        TrendingPostCommand::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command("process:post_handle")->everyMinute();
        $schedule->command("process:trending_tags_handle")->everyThreeMinutes();
        // $schedule->command('inspire')->hourly();
        $schedule->command('members:update-status')->everyMinute();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
