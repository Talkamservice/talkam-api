<?php

namespace App\Console;

use App\Console\Commands\Finance\Currency\UpdateCurrencyRatesCommand;
use App\Console\Commands\Group\UpdateSuspendedMembersStatus;
use App\Console\Commands\Notification\SendPendingNotificationCommand;
use App\Console\Commands\Post\PostCommand;
use App\Console\Commands\Post\PostStatsCommand;
use App\Console\Commands\Post\TrendingPostCommand;
use App\Console\Commands\Promotion\SendPromotionNotification;
use App\Console\Commands\TestCommand;
use App\Console\Commands\TestGroupMemberRemoval;
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
        UpdateSuspendedMembersStatus::class,
        SendPendingNotificationCommand::class,
        UpdateCurrencyRatesCommand::class,
        TestGroupMemberRemoval::class,
        PostStatsCommand::class,
        SendPromotionNotification::class
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
        $schedule->command('notifications:send-pending')->everyMinute();
        $schedule->command('announcements:update-expired')->everyMinute();
        $schedule->command('promotions:notify-pending')->everyMinute();
        $schedule->command('promotion:send-notifications')->dailyAt('00:00');
        $schedule->command('promotion:send-expired-notifications')->everyMinute();
        $schedule->command('finance:currency_rates')->weekly();
        $schedule->command('process:post-stats-command')->everyThreeHours();
        $schedule->command('telescope:prune')->everySixHours();
        $schedule->command('bookings:release-expired-holds')->everyMinute();
        $schedule->command('bookings:send-session-reminders')->everyMinute();
        $schedule->command('bookings:sweep-session-completions')->everyMinute();
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
