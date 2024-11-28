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
        // Get the current time
        $now = Carbon::now();

        // Get all pending promotions
        $promotions = Promotion::with(['group', 'post'])->where('status', StatusConstants::PENDING)->get();

        foreach ($promotions as $promotion) {
            // Calculate the promotion's expiration time
            $expiresAt = Carbon::parse($promotion->created_at)->addDays(3);  // 3 days after creation
            $hoursRemaining = $now->diffInHours($expiresAt, false); // Negative if past expiry

            // Ensure that last_reminder_sent_at is a Carbon instance
            $lastReminderSentAt = $promotion->last_reminder_sent_at ? Carbon::parse($promotion->last_reminder_sent_at) : null;

            if ($promotion->last_reminder_sent_at === null) {
                // Send an immediate notification to the user about the pending promotion
                Notification::send($promotion->user, new PromotionExpiryReminder($promotion));

                // Update last reminder sent time
                $promotion->update(['last_reminder_sent_at' => $now]);

                Log::info("Initial reminder sent to user for promotion ID: {$promotion->id}.");
            }

            if ($hoursRemaining > 0 && $hoursRemaining <= 72) {
                // Check if it's been 24 hours since the last reminder
                if ($lastReminderSentAt && $lastReminderSentAt->diffInHours($now) >= 24) {
                    // Send a periodic reminder notification
                    Notification::send($promotion->user, new PromotionExpiryReminder($promotion));

                    // Update last reminder sent time
                    $promotion->update(['last_reminder_sent_at' => $now]);

                    Log::info("Reminder sent to user for promotion ID: {$promotion->id}, expires in {$hoursRemaining} hours.");
                }
            } elseif ($hoursRemaining <= 0) {
                // Send final expiration notification if the promotion has expired
                Notification::send($promotion->user, new PromotionExpiryReminder($promotion, true)); // Pass true to signify expiration

                // Update the promotion status to expired
                $promotion->update([
                    'status' => StatusConstants::INACTIVE,
                    'last_reminder_sent_at' => $now,
                ]);

                Log::info("Promotion expired for promotion ID: {$promotion->id}.");
            }
        }

        $this->info('Promotion notifications processed successfully.');
    }
}
