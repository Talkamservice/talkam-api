<?php

namespace App\Console\Commands\Promotion;

use App\Constants\General\StatusConstants;
use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\Promotion;
use App\Notifications\Promotion\PromotionExpiryReminder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;

class NotifyPendingPromotions extends Command
{
    protected $signature = 'promotions:notify-pending';
    protected $description = 'Send notifications for promotions that will expire soon';

    public function handle()
    {
        // Set the times for reminders and final expiration notifications
        $threeDaysFromNow = Carbon::now()->addDays(3);
        $now = Carbon::now();

        // Get all pending promotions
        $promotions = Promotion::where('status', StatusConstants::PENDING)->get();

        foreach ($promotions as $promotion) {
            // Calculate the promotion's expiration time
            $expiresAt = Carbon::parse($promotion->created_at)->addDays($promotion->duration);
            $hoursRemaining = $now->diffInHours($expiresAt, false); // Negative if past expiry

            if ($hoursRemaining > 0 && $hoursRemaining <= 72) {
                // Check if we are in the 3-day window and if 12 hours have passed since last notification
                if ($promotion->last_reminder_sent_at === null || $promotion->last_reminder_sent_at->diffInHours($now) >= 12) {
                    Notification::send($promotion->user, new PromotionExpiryReminder($promotion));

                    // Update last reminder sent time
                    $promotion->update(['last_reminder_sent_at' => $now]);

                    // Log the reminder notification
                    // Log::info("Reminder sent to user for promotion ID: {$promotion->id}, expires in {$hoursRemaining} hours.");
                }
            } elseif ($hoursRemaining <= 0) {
                // Send final expiration notification if the promotion has expired
                Notification::send($promotion->user, new PromotionExpiryReminder($promotion, true)); // Pass true to signify expiration

                // Update the promotion status to expired
                $promotion->update([
                    'status' => StatusConstants::INACTIVE,
                    'last_reminder_sent_at' => $now,
                ]);

            }
        }

        $this->info('Promotion notifications processed successfully.');
    }
}
