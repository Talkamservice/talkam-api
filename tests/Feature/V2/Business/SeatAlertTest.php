<?php

namespace Tests\Feature\V2\Business;

use App\Constants\Business\OrganizationConstants as OC;
use App\Models\NotificationPreference;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Notifications\Business\SeatLimitNotification;
use App\Services\Business\SeatAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** The seat-limit alert sweep (web §03 Settings). */
class SeatAlertTest extends TestCase
{
    use RefreshDatabase;

    private function orgWithAdmin(int $seats_licensed): array
    {
        $admin = User::factory()->create();
        $org = Organization::create([
            "name" => "Meridian Health", "slug" => "meridian-" . uniqid(),
            "domain" => "meridian" . uniqid() . ".ng", "status" => OC::STATUS_ACTIVE,
            "seats_licensed" => $seats_licensed, "verified_at" => now(),
        ]);
        OrganizationMember::create([
            "organization_id" => $org->id, "user_id" => $admin->id,
            "role" => OC::ROLE_ADMIN, "status" => OC::MEMBER_ACTIVE, "activated_at" => now(),
        ]);

        return [$org, $admin];
    }

    private function fillSeats(Organization $org, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            OrganizationMember::create([
                "organization_id" => $org->id, "user_id" => User::factory()->create()->id,
                "role" => OC::ROLE_EMPLOYEE, "status" => OC::MEMBER_ACTIVE, "activated_at" => now(),
            ]);
        }
    }

    public function test_alerts_when_below_the_threshold(): void
    {
        Notification::fake();
        [$org, $admin] = $this->orgWithAdmin(10);
        $this->fillSeats($org, 8); // + the admin's own seat = 9 of 10 used, 1 remaining = 10% -> at the floor

        $result = SeatAlertService::sweep();

        $this->assertSame(1, $result["alerted"]);
        Notification::assertSentTo(
            $admin,
            SeatLimitNotification::class,
            fn ($n) => $n->remaining === 1 && $n->seatsLicensed === 10
        );
        $this->assertNotNull($org->refresh()->seat_alert_sent_at);
    }

    public function test_does_not_alert_above_the_threshold(): void
    {
        Notification::fake();
        [$org, $admin] = $this->orgWithAdmin(10);
        $this->fillSeats($org, 5); // + the admin's own seat = 6 of 10 used, 4 remaining = 40%

        SeatAlertService::sweep();

        Notification::assertNotSentTo($admin, SeatLimitNotification::class);
        $this->assertNull($org->refresh()->seat_alert_sent_at);
    }

    public function test_is_one_shot_then_resets_once_seats_free_up(): void
    {
        Notification::fake();
        [$org, $admin] = $this->orgWithAdmin(10);
        $this->fillSeats($org, 8); // + the admin's own seat = 9 of 10 used, 1 remaining

        SeatAlertService::sweep(); // fires once
        SeatAlertService::sweep(); // dip persists -> no repeat

        Notification::assertSentToTimes($admin, SeatLimitNotification::class, 1);

        // Free up a seat -> headroom recovers above the threshold -> flag resets.
        OrganizationMember::where("organization_id", $org->id)
            ->where("role", OC::ROLE_EMPLOYEE)
            ->first()
            ->update(["status" => OC::MEMBER_INACTIVE]);

        SeatAlertService::sweep();
        $this->assertNull($org->refresh()->seat_alert_sent_at);

        // A fresh dip below the threshold alerts again.
        $this->fillSeats($org, 1);
        SeatAlertService::sweep();
        Notification::assertSentToTimes($admin, SeatLimitNotification::class, 2);
    }

    public function test_respects_the_admins_opt_out(): void
    {
        Notification::fake();
        [$org, $admin] = $this->orgWithAdmin(10);
        $this->fillSeats($org, 8); // + the admin's own seat = 9 of 10 used, 1 remaining
        NotificationPreference::create(["user_id" => $admin->id, "seat_limit_alerts" => 0]);

        SeatAlertService::sweep();

        Notification::assertNotSentTo($admin, SeatLimitNotification::class);
        // Still marked handled — an org isn't re-checked forever just because nobody's subscribed.
        $this->assertNotNull($org->refresh()->seat_alert_sent_at);
    }
}
