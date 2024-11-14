<?php

namespace App\Console\Commands\Promotion;

use App\Constants\General\StatusConstants;
use Illuminate\Console\Command;
use App\Models\Promotion;
use App\Notifications\Promotion\PromotionImpressionNotification;
use Illuminate\Support\Facades\Notification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SendPromotionNotification extends Command
{
    protected $signature = 'promotion:send-notifications';
    protected $description = 'Send daily notifications to users about their promoted content impressions';

    public function handle()
    {
        // Retrieve active promotions (that have not expired)
        $promotions = Promotion::where('status', StatusConstants::ACTIVE)
            ->whereDate('created_at', '<=', Carbon::now())  // Ensure promotions have been created
            ->get();
        foreach ($promotions as $promotion) {
            // Calculate expiration date based on promotion's duration
            $expiresAt = Carbon::parse($promotion->created_at)->addDays($promotion->duration);

            // Check if the promotion has expired
            if (Carbon::now()->lt($expiresAt)) {
                // Retrieve promotion type and impressions
                $type = $promotion->type();
                $impressions = $promotion->statAttribute('impressions');

                // Construct the notification message
                $message = "Your {$type} ad has {$impressions} impressions. Click the link below to view analytics.";

                // Send the notification to the user
                Notification::send($promotion->user, new PromotionImpressionNotification($promotion, $message));

            }
        }

        $this->info('Promotion notifications have been sent successfully.');
    }
}
