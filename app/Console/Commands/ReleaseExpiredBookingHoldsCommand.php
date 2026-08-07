<?php

namespace App\Console\Commands;

use App\Constants\Therapist\TherapistConstants;
use App\Models\TherapySession;
use Illuminate\Console\Command;

class ReleaseExpiredBookingHoldsCommand extends Command
{
    protected $signature = 'bookings:release-expired-holds';

    protected $description = 'Expire pending_payment bookings whose slot hold has lapsed so abandoned checkouts never block a slot';

    public function handle(): int
    {
        $released = TherapySession::where('status', TherapistConstants::SESSION_PENDING_PAYMENT)
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<=', now())
            ->update(['status' => TherapistConstants::SESSION_EXPIRED]);

        $this->info("Released $released expired booking hold(s).");

        return self::SUCCESS;
    }
}
