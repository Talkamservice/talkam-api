<?php

namespace App\Console\Commands\Promotion;

use App\Constants\General\StatusConstants;
use Illuminate\Console\Command;
use App\Models\Promotion;
use App\Notifications\Promotion\PromotionImpressionNotification;
use Illuminate\Support\Facades\Notification;
use Carbon\Carbon;

class SendExpiredPromotionNotification extends Command
{
    protected $signature = 'promotion:send-expired-notifications';
    protected $description = 'Send notifications to users if their promotion has expired';

    public function handle()
    {
        // Retrieve active promotions
        $promotions = Promotion::where('status', StatusConstants::ACTIVE)
            ->get();

        foreach ($promotions as $promotion) {
            // Calculate expiration date based on promotion's duration
            $expiresAt = Carbon::parse($promotion->created_at)->addDays($promotion->duration);

            // Check if the promotion has expired
            if (Carbon::now()->gte($expiresAt)) {
                // Retrieve promotion type and impressions
                $type = $promotion->type();
               
                // Construct the notification message
                $message = "Your {$type} ad has expired. Click to view the final analytics of this ad.";

                // Send the notification to the user
                Notification::send($promotion->user, new PromotionImpressionNotification($promotion, $message));

                $this->info('Expired notification sent to: ' . $promotion->user->email);
            }
        }

        $this->info('Expired promotion notifications have been sent successfully.');
    }
}
