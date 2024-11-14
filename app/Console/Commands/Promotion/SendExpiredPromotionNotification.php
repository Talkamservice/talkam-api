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
        $promotions = Promotion::where('status', StatusConstants::ACTIVE)->get();

        foreach ($promotions as $promotion) {
            $expiresAt = Carbon::parse($promotion->created_at)->addDays($promotion->duration);

            if (Carbon::now()->gte($expiresAt)) {
                $type = $promotion->type();
                $message = "Your {$type} ad has expired. Click to view the final analytics of this ad.";
                $title = "Your {$type} ad has expired";
                Notification::send($promotion->user, new PromotionImpressionNotification($promotion, $message, $title));
            }

            $promotion->update([
                "status" => StatusConstants::COMPLETED
            ]);
        }
    }
}
