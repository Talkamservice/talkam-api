<?php

namespace Tests\Feature\V2\Booking;

use App\Models\TherapySession;
use App\Models\User;
use App\Notifications\Therapist\SessionReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SessionReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_reminds_confirmed_sessions_once_at_lead_time(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $due = TherapySession::factory()->create([
            "user_id" => $user->id,
            "starts_at" => now()->addMinutes(20),
        ]);
        $far_off = TherapySession::factory()->create([
            "starts_at" => now()->addHours(3),
        ]);
        $not_confirmed = TherapySession::factory()->pending()->create([
            "starts_at" => now()->addMinutes(20),
        ]);

        $this->artisan("bookings:send-session-reminders")->assertSuccessful();

        Notification::assertSentTo($user, SessionReminderNotification::class);
        $this->assertNotNull($due->refresh()->reminded_at);
        $this->assertNull($far_off->refresh()->reminded_at);
        $this->assertNull($not_confirmed->refresh()->reminded_at);

        // Second run sends no duplicates.
        Notification::fake();
        $this->artisan("bookings:send-session-reminders")->assertSuccessful();
        Notification::assertNothingSent();
    }
}
