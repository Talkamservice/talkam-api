<?php

namespace App\Console\Commands;

use App\Constants\Therapist\TherapistConstants;
use App\Models\TherapySession;
use App\Notifications\Therapist\SessionReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendSessionRemindersCommand extends Command
{
    protected $signature = 'bookings:send-session-reminders';

    protected $description = 'Push a reminder for confirmed sessions starting within the configured lead time (once per session)';

    public function handle(): int
    {
        $lead = config('therapist.booking.reminder_lead_minutes');

        $sessions = TherapySession::with(['user', 'therapist.user'])
            ->where('status', TherapistConstants::SESSION_CONFIRMED)
            ->whereNull('reminded_at')
            ->whereBetween('starts_at', [now(), now()->addMinutes($lead)])
            ->get();

        foreach ($sessions as $session) {
            Notification::send($session->user, new SessionReminderNotification($session));
            if (!empty($session->therapist?->user)) {
                Notification::send($session->therapist->user, new SessionReminderNotification($session));
            }
            $session->update(['reminded_at' => now()]);
        }

        $this->info("Sent {$sessions->count()} session reminder(s).");

        return self::SUCCESS;
    }
}
