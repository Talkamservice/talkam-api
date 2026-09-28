<?php

namespace Tests\Feature\V2\Commands;

use App\Models\MoodCheckin;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\User\WellnessCheckinNudgeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WellnessNudgeCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Pin the clock to the configured send hour.
        $this->travelTo(now()->setTime(config("v2.wellness_nudge.hour"), 0));
    }

    public function test_user_without_checkin_today_gets_one_nudge(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->artisan("wellness:send-checkin-nudges")->assertSuccessful();

        Notification::assertSentTo($user, WellnessCheckinNudgeNotification::class);
    }

    public function test_user_with_checkin_today_gets_none(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        MoodCheckin::factory()->create(["user_id" => $user->id]);

        $this->artisan("wellness:send-checkin-nudges")->assertSuccessful();

        Notification::assertNotSentTo($user, WellnessCheckinNudgeNotification::class);
    }

    public function test_disabled_preference_suppresses_nudge(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        NotificationPreference::create(["user_id" => $user->id, "wellness_nudges" => 0]);

        $this->artisan("wellness:send-checkin-nudges")->assertSuccessful();

        Notification::assertNotSentTo($user, WellnessCheckinNudgeNotification::class);
    }

    public function test_double_run_same_day_sends_at_most_once(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->artisan("wellness:send-checkin-nudges")->assertSuccessful();
        $this->artisan("wellness:send-checkin-nudges")->assertSuccessful();

        Notification::assertSentToTimes($user, WellnessCheckinNudgeNotification::class, 1);
    }

    public function test_send_hour_read_from_config(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        // Off-hour: nothing fires.
        config(["v2.wellness_nudge.hour" => now()->hour + 1]);
        $this->artisan("wellness:send-checkin-nudges")->assertSuccessful();
        Notification::assertNotSentTo($user, WellnessCheckinNudgeNotification::class);

        // Matching hour: fires.
        config(["v2.wellness_nudge.hour" => now()->hour]);
        $this->artisan("wellness:send-checkin-nudges")->assertSuccessful();
        Notification::assertSentTo($user, WellnessCheckinNudgeNotification::class);
    }
}
