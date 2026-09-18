<?php

namespace Tests\Feature\V2\Admin;

use App\Constants\Business\OrganizationConstants as OC;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** The admin Settings screen's Notification Preferences card (web §03). */
class AdminNotificationPreferenceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $org = Organization::create([
            "name" => "Co", "slug" => "co-" . uniqid(), "domain" => "co" . uniqid() . ".ng",
            "status" => OC::STATUS_ACTIVE, "verified_at" => now(),
        ]);
        OrganizationMember::create([
            "organization_id" => $org->id, "user_id" => $admin->id,
            "role" => OC::ROLE_ADMIN, "status" => OC::MEMBER_ACTIVE, "activated_at" => now(),
        ]);

        return $admin;
    }

    public function test_get_returns_the_deck_defaults(): void
    {
        Sanctum::actingAs($this->admin());

        $data = $this->getJson("/api/v2/business/notification-preferences")
            ->assertStatus(200)->json("data");

        foreach (["digest_summary", "seat_limit_alerts", "invoice_notifications"] as $key) {
            $this->assertTrue($data[$key], "$key should default on");
        }
        $this->assertFalse($data["new_therapist_announcements"], "therapist announcements should default off");
    }

    public function test_post_toggles_each_key(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $this->postJson("/api/v2/business/notification-preferences", [
            "seat_limit_alerts" => false,
            "new_therapist_announcements" => true,
        ])->assertStatus(200)
            ->assertJsonPath("data.seat_limit_alerts", false)
            ->assertJsonPath("data.new_therapist_announcements", true)
            ->assertJsonPath("data.digest_summary", true); // untouched keys are unaffected

        $this->assertDatabaseHas("notification_preferences", [
            "user_id" => $admin->id,
            "seat_limit_alerts" => 0,
            "new_therapist_announcements" => 1,
        ]);
    }

    public function test_unknown_key_rejected(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v2/business/notification-preferences", [
            "mystery_toggle" => true,
        ])->assertStatus(422)->assertJson(["success" => false]);
    }

    public function test_an_employee_is_refused(): void
    {
        $employee = User::factory()->create();
        $org = Organization::create([
            "name" => "Co", "slug" => "co-" . uniqid(), "domain" => "co" . uniqid() . ".ng",
            "status" => OC::STATUS_ACTIVE, "verified_at" => now(),
        ]);
        OrganizationMember::create([
            "organization_id" => $org->id, "user_id" => $employee->id,
            "role" => OC::ROLE_EMPLOYEE, "status" => OC::MEMBER_ACTIVE, "activated_at" => now(),
        ]);
        Sanctum::actingAs($employee);

        $this->getJson("/api/v2/business/notification-preferences")->assertForbidden();
    }

    public function test_preferences_are_personal_not_org_wide(): void
    {
        $admin_one = $this->admin();
        $organization_id = OrganizationMember::where("user_id", $admin_one->id)->first()->organization_id;

        $admin_two = User::factory()->create();
        OrganizationMember::create([
            "organization_id" => $organization_id, "user_id" => $admin_two->id,
            "role" => OC::ROLE_ADMIN, "status" => OC::MEMBER_ACTIVE, "activated_at" => now(),
        ]);

        Sanctum::actingAs($admin_one);
        $this->postJson("/api/v2/business/notification-preferences", ["digest_summary" => false])
            ->assertStatus(200);

        Sanctum::actingAs($admin_two);
        $data = $this->getJson("/api/v2/business/notification-preferences")->json("data");
        $this->assertTrue($data["digest_summary"], "the second admin's own preference is untouched");
    }
}
