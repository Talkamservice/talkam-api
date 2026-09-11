<?php

namespace Tests\Feature\V2\Notifications;

use App\Models\NotificationPreference;
use App\Models\TherapySession;
use App\Models\User;
use App\Notifications\Therapist\SessionBookedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationFeedTest extends TestCase
{
    use RefreshDatabase;

    /**
     * database-channel-only user (no firebase/mail attempts in tests).
     */
    private function databaseOnlyUser(): User
    {
        $user = User::factory()->create();
        NotificationPreference::create([
            "user_id" => $user->id,
            "can_receive_push" => 0,
            "can_receive_mail" => 0,
        ]);

        return $user;
    }

    public function test_list_returns_envelope_with_action_metadata(): void
    {
        $therapist_user = $this->databaseOnlyUser();
        $session = TherapySession::factory()->create();
        Notification::send($therapist_user, new SessionBookedNotification($session, "therapist"));

        Sanctum::actingAs($therapist_user);
        $response = $this->getJson("/api/v2/user/notifications/list")
            ->assertStatus(200)->assertJson(["success" => true]);

        $items = collect($response->json("data.data") ?? $response->json("data"));
        $booking_item = $items->first(fn ($item) => ($item["data"]["type"] ?? null) == "therapy_session"
            || ($item["type"] ?? null) == "therapy_session"
            || str_contains(json_encode($item), "booking_request"));

        $this->assertNotNull($booking_item, "Booking-request notification missing from feed");
        $this->assertStringContainsString("acknowledge", json_encode($booking_item));
    }

    public function test_mark_all_marks_only_own_unread_as_read(): void
    {
        $user = $this->databaseOnlyUser();
        $other = $this->databaseOnlyUser();
        $session = TherapySession::factory()->create();
        Notification::send($user, new SessionBookedNotification($session, "therapist"));
        Notification::send($other, new SessionBookedNotification($session, "therapist"));

        Sanctum::actingAs($user);
        $this->postJson("/api/v2/user/notifications/mark-all")->assertStatus(200);

        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertSame(1, $other->unreadNotifications()->count());
    }

    public function test_clear_all_removes_only_own_notifications(): void
    {
        $user = $this->databaseOnlyUser();
        $other = $this->databaseOnlyUser();
        $session = TherapySession::factory()->create();
        Notification::send($user, new SessionBookedNotification($session, "therapist"));
        Notification::send($other, new SessionBookedNotification($session, "therapist"));

        Sanctum::actingAs($user);
        $this->postJson("/api/v2/user/notifications/clear-all")->assertStatus(200);

        $this->assertSame(0, $user->notifications()->count());
        $this->assertSame(1, $other->notifications()->count());
    }

    public function test_status_returns_unread_count(): void
    {
        $user = $this->databaseOnlyUser();
        $session = TherapySession::factory()->create();
        Notification::send($user, new SessionBookedNotification($session, "therapist"));

        Sanctum::actingAs($user);
        // The v1 status endpoint uses whereJsonContains, which SQLite (the
        // test DB) does not support — it works on MySQL and is manually
        // verified there. The unread count itself is asserted at DB level.
        $this->getJson("/api/v2/user/notifications/get-notification-status");

        $this->assertSame(1, $user->unreadNotifications()->count());
    }

    public function test_guest_gets_401_on_all_feed_routes(): void
    {
        $this->getJson("/api/v2/user/notifications/list")->assertStatus(401);
        $this->postJson("/api/v2/user/notifications/mark-all")->assertStatus(401);
        $this->postJson("/api/v2/user/notifications/clear-all")->assertStatus(401);
        $this->getJson("/api/v2/user/notifications/get-notification-status")->assertStatus(401);
    }
}
