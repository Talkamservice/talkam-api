<?php

namespace App\Console\Commands\Promotion;

use App\Constants\General\StatusConstants;
use Illuminate\Console\Command;
use App\Models\Promotion;
use App\Notifications\Promotion\PromotionImpressionNotification;
use App\Services\Promotion\PromotionService;
use Illuminate\Support\Facades\Notification;
use Carbon\Carbon;

class SendPromotionNotification extends Command
{
    protected $signature = 'promotion:send-notifications';
    protected $description = 'Send daily notifications to users about their promoted content impressions';

    public function handle()
    {
        $promotions = Promotion::where('status', StatusConstants::ACTIVE)
            ->whereDate('created_at', '<=', Carbon::now())  // Ensure promotions have been created
            ->get();

        foreach ($promotions as $promotion) {
            $expiresAt = Carbon::parse($promotion->created_at)->addDays($promotion->duration);

            if (Carbon::now()->lt($expiresAt)) {
                $type = $promotion->type();

                $impressions = (new PromotionService)
                    ->promotionStats($promotion, "impressions");

                $message = "Your {$type} ad has {$impressions} impressions. Click to view the analytics of this ad.";

                Notification::send($promotion->user, new PromotionImpressionNotification($promotion, $message));
            }
        }

        $this->info('Promotion notifications have been sent successfully.');
    }
}
