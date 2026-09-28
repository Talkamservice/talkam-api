<?php

namespace App\Console\Commands;

use App\Models\MoodCheckin;
use App\Models\User;
use App\Notifications\User\WellnessCheckinNudgeNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

class SendWellnessCheckinNudgesCommand extends Command
{
    protected $signature = 'wellness:send-checkin-nudges {--force : Ignore the configured send hour}';

    protected $description = 'Nudge users who have not logged a mood check-in today (config hour, once daily, preference-honoring)';

    public function handle(): int
    {
        $send_hour = (int) config('v2.wellness_nudge.hour');

        if (!$this->option('force') && now()->hour != $send_hour) {
            $this->info("Outside the configured send hour ($send_hour); nothing to do.");
            return self::SUCCESS;
        }

        $today = now()->toDateString();
        $checked_in = MoodCheckin::where('checked_in_on', $today)->pluck('user_id');

        $users = User::whereNotIn('id', $checked_in)
            ->whereDoesntHave('notificationPreference', fn ($q) => $q->where('wellness_nudges', 0))
            ->get();

        $sent = 0;
        foreach ($users as $user) {
            // Once-daily cap survives repeated runs.
            if (!Cache::add("wellness_nudge:{$user->id}:{$today}", true, now()->addDay())) {
                continue;
            }

            Notification::send($user, new WellnessCheckinNudgeNotification);
            $sent++;
        }

        $this->info("Sent $sent wellness nudge(s).");

        return self::SUCCESS;
    }
}
