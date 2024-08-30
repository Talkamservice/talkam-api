<?php

namespace App\Jobs;

use App\Models\SendBulkNotification;
use App\Models\User;
use App\Notifications\Bulk\SendbulkUsersNotification;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendUserNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    /**
     * Create a new job instance.
     */
    protected $chunkSize;
    protected $notification;
    public function __construct(SendBulkNotification $notification, $chunkSize = 500)
    {
        $this->notification = $notification;
        $this->chunkSize = $chunkSize;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $userIds = $this->notification->recipients()->pluck('user_id')->toArray();

        // Process users in chunks
        foreach (array_chunk($userIds, $this->chunkSize) as $chunk) {
            $users = User::whereIn('id', $chunk)->get();

            foreach ($users as $user) {
                $user->notify(new SendbulkUsersNotification($user, $this->notification));
            }
        }
    }
}
