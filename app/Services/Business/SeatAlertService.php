<?php

namespace App\Services\Business;

use App\Constants\Business\OrganizationConstants;
use App\Models\Organization;
use App\Notifications\Business\SeatLimitNotification;
use Illuminate\Support\Facades\Notification;

/**
 * The seat-limit alert sweep (web §03 Settings → "Seat limit alerts"). Fires
 * once per dip below the threshold (guarded by seat_alert_sent_at); resets
 * once seats free back up above it, so a later dip alerts again.
 */
class SeatAlertService
{
    public static function sweep(): array
    {
        $threshold = (float) config('business.seat_alert_threshold_percent');
        $orgs = Organization::where('status', OrganizationConstants::STATUS_ACTIVE)
            ->whereNotNull('verified_at')
            ->where('seats_licensed', '>', 0)
            ->get();

        $alerted = 0;
        $reset = 0;

        foreach ($orgs as $org) {
            $remaining = (int) $org->seats_licensed - $org->seatsUsed();
            $percent_remaining = ($remaining / $org->seats_licensed) * 100;

            if ($percent_remaining > $threshold) {
                if ($org->seat_alert_sent_at) {
                    $org->update(['seat_alert_sent_at' => null]);
                    $reset++;
                }
                continue;
            }

            if ($org->seat_alert_sent_at) {
                continue; // already alerted for this dip
            }

            $recipients = AdminNotificationGateService::subscribedAdminsForOrg($org, 'seat_limit_alerts');

            if ($recipients->isNotEmpty()) {
                Notification::send(
                    $recipients,
                    new SeatLimitNotification($org->name, max($remaining, 0), (int) $org->seats_licensed)
                );
            }

            $org->update(['seat_alert_sent_at' => now()]);
            $alerted++;
        }

        return ['orgs' => $orgs->count(), 'alerted' => $alerted, 'reset' => $reset];
    }
}
