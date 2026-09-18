<?php

namespace Tests\Feature\V2\Business;

use App\Constants\Business\OrganizationConstants as OC;
use App\Constants\Therapist\TherapistConstants;
use App\Models\NotificationPreference;
use App\Models\Organization;
use App\Models\OrganizationDigest;
use App\Models\OrganizationMember;
use App\Models\Therapist;
use App\Models\TherapySession;
use App\Models\User;
use App\Notifications\Business\OrganizationDigestNotification;
use App\Services\Business\OrganizationDigestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** The monthly usage-digest email (web §03 Settings). */
class OrganizationDigestTest extends TestCase
{
    use RefreshDatabase;

    private function orgWithAdmin(): array
    {
        $admin = User::factory()->create();
        $org = Organization::create([
            "name" => "Meridian Health", "slug" => "meridian-" . uniqid(),
            "domain" => "meridian" . uniqid() . ".ng", "status" => OC::STATUS_ACTIVE,
            "verified_at" => now(),
        ]);
        OrganizationMember::create([
            "organization_id" => $org->id, "user_id" => $admin->id,
            "role" => OC::ROLE_ADMIN, "status" => OC::MEMBER_ACTIVE, "activated_at" => now(),
        ]);

        return [$org, $admin];
    }

    private function addEmployee(Organization $org): User
    {
        $employee = User::factory()->create();
        OrganizationMember::create([
            "organization_id" => $org->id, "user_id" => $employee->id,
            "role" => OC::ROLE_EMPLOYEE, "status" => OC::MEMBER_ACTIVE, "activated_at" => now(),
        ]);

        return $employee;
    }

    private function lastMonth(): array
    {
        $start = now()->subMonthNoOverflow()->startOfMonth();
        return [$start, $start->copy()->endOfMonth()];
    }

    public function test_digest_is_sent_to_subscribed_admins_with_summary_content(): void
    {
        Notification::fake();
        [$org, $admin] = $this->orgWithAdmin();
        $employee = $this->addEmployee($org);

        $therapist = Therapist::factory()->create(["user_id" => User::factory()->create()->id]);
        TherapySession::create([
            "uuid" => strtoupper(uniqid()), "user_id" => $employee->id, "therapist_id" => $therapist->id,
            "starts_at" => now()->subMonthNoOverflow()->startOfMonth()->addDays(5),
            "duration_minutes" => 50, "format" => "video",
            "status" => TherapistConstants::SESSION_COMPLETED, "amount" => 15000,
        ]);

        [$start, $end] = $this->lastMonth();
        $result = OrganizationDigestService::run($start, $end);

        $this->assertSame(1, $result["sent"]);
        Notification::assertSentTo(
            $admin,
            OrganizationDigestNotification::class,
            fn ($n) => $n->summary["sessions_completed"]["value"] === null // below the 5-person cohort floor -> suppressed
        );
    }

    public function test_digest_skips_an_org_with_no_employees(): void
    {
        Notification::fake();
        [$org] = $this->orgWithAdmin(); // admin only, no employees

        [$start, $end] = $this->lastMonth();
        $result = OrganizationDigestService::run($start, $end);

        $this->assertSame(0, $result["sent"]);
        $this->assertSame(0, OrganizationDigest::where("organization_id", $org->id)->count());
    }

    public function test_digest_is_idempotent_per_period(): void
    {
        Notification::fake();
        [$org, $admin] = $this->orgWithAdmin();
        $this->addEmployee($org);

        [$start, $end] = $this->lastMonth();
        OrganizationDigestService::run($start, $end);
        OrganizationDigestService::run($start, $end);

        Notification::assertSentToTimes($admin, OrganizationDigestNotification::class, 1);
    }

    public function test_digest_respects_the_admins_opt_out(): void
    {
        Notification::fake();
        [$org, $admin] = $this->orgWithAdmin();
        $this->addEmployee($org);
        NotificationPreference::create(["user_id" => $admin->id, "digest_summary" => 0]);

        [$start, $end] = $this->lastMonth();
        OrganizationDigestService::run($start, $end);

        Notification::assertNotSentTo($admin, OrganizationDigestNotification::class);
    }
}
