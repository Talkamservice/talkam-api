<?php

namespace App\Console\Commands\Notification;

use App\Constants\General\StatusConstants;
use App\Jobs\SendUserNotificationJob;
use App\Models\SendBulkNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendPendingNotificationCommand extends Command
{
    // Command signature
    protected $signature = 'notifications:send-pending';

    // Command description
    protected $description = 'Send pending notifications that are pending and scheduled to be sent.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // Fetch all notifications that are not sent and have a schedule date in the past
        $pendingNotifications = SendBulkNotification::where('status', StatusConstants::PENDING)
            ->where('schedule_date', '<=', Carbon::now())
            ->get();

        foreach ($pendingNotifications as $notification) {
            // Dispatch jobs for each notification
            $chunkSize = 1000;
            $userIds = $notification->recipients()->pluck('user_id')->toArray();

            foreach (array_chunk($userIds, $chunkSize) as $chunk) {
                $job = new SendUserNotificationJob($notification, $chunkSize);
                dispatch($job);
            }

            // Update notification status to Sent
            $notification->update(['status' => StatusConstants::SENT]);

            $this->info("Notification ID {$notification->id} has been sent and status updated.");
        }

        $this->info("All pending notifications have been processed.");
    }
}
