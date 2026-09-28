<?php

namespace Tests\Feature\V2\Business;

use App\Constants\Auth\PinConstants;
use App\Constants\Business\OrganizationConstants;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Pin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DomainVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function unverifiedAdmin(): array
    {
        $organization = Organization::factory()->unverified()->create();
        $user = User::factory()->unverified()->create();

        OrganizationMember::factory()->admin()->create([
            "organization_id" => $organization->id,
            "user_id" => $user->id,
        ]);

        return [$organization, $user];
    }

    private function issuePin(User $user): Pin
    {
        return Pin::create([
            "user_id" => $user->id,
            "code" => "472913",
            "type" => PinConstants::TYPE_VERIFY_EMAIL,
            "expires_at" => now()->addMinutes(15),
        ]);
    }

    public function test_correct_code_confirms_the_domain_and_the_email(): void
    {
        [$organization, $user] = $this->unverifiedAdmin();
        $this->issuePin($user);
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/business/domain/verify", ["code" => "472913"])
            ->assertStatus(200)
            ->assertJson(["success" => true]);

        $organization->refresh();
        $user->refresh();

        $this->assertNotNull($organization->verified_at);
        $this->assertSame(OrganizationConstants::STATUS_ACTIVE, $organization->status);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_wrong_code_changes_nothing(): void
    {
        [$organization, $user] = $this->unverifiedAdmin();
        $this->issuePin($user);
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/business/domain/verify", ["code" => "000000"])
            ->assertStatus(400)
            ->assertJson(["success" => false]);

        $this->assertNull($organization->refresh()->verified_at);
        $this->assertNull($user->refresh()->email_verified_at);
    }

    public function test_expired_code_is_rejected(): void
    {
        [$organization, $user] = $this->unverifiedAdmin();

        Pin::create([
            "user_id" => $user->id,
            "code" => "472913",
            "type" => PinConstants::TYPE_VERIFY_EMAIL,
            "expires_at" => now()->subMinute(),
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v2/business/domain/verify", ["code" => "472913"])
            ->assertStatus(400);

        $this->assertNull($organization->refresh()->verified_at);
    }

    /** Another company's admin cannot burn a code to verify their own org. */
    public function test_a_code_issued_to_another_user_cannot_verify_this_org(): void
    {
        [$organization, $user] = $this->unverifiedAdmin();

        $other = User::factory()->create();
        $this->issuePin($other);

        Sanctum::actingAs($user);

        $this->postJson("/api/v2/business/domain/verify", ["code" => "472913"])
            ->assertStatus(400);

        $this->assertNull($organization->refresh()->verified_at);
    }

    public function test_seats_plan_and_invites_are_blocked_until_the_domain_is_confirmed(): void
    {
        [$organization, $user] = $this->unverifiedAdmin();
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/business/organization/seats", [
            "seats_licensed" => 250,
            "therapist_access" => true,
            "bundle_sessions" => 25,
        ])->assertStatus(403);

        $this->postJson("/api/v2/business/organization/plan", ["pay_method" => "invoice"])
            ->assertStatus(403);

        $this->postJson("/api/v2/business/invitations", [
            "invites" => [["email" => "someone@" . $organization->domain, "role" => "employee"]],
        ])->assertStatus(403);

        $this->assertSame(0, (int) $organization->refresh()->seats_licensed);
    }

    public function test_bench_and_organization_read_work_before_verification(): void
    {
        [, $user] = $this->unverifiedAdmin();
        Sanctum::actingAs($user);

        $this->getJson("/api/v2/business/organization")->assertStatus(200);
        $this->postJson("/api/v2/business/organization/bench", ["bench_topics" => ["anxiety"]])
            ->assertStatus(200);
    }

    public function test_resend_reuses_the_shared_otp_endpoint(): void
    {
        [, $user] = $this->unverifiedAdmin();

        $this->postJson("/api/v2/auth/otp/request", [
            "type" => PinConstants::TYPE_VERIFY_EMAIL,
            "email" => $user->email,
        ])->assertStatus(200);

        $pin = Pin::where("user_id", $user->id)->where("type", PinConstants::TYPE_VERIFY_EMAIL)->first();
        $this->assertNotNull($pin);
        $this->assertSame(6, strlen((string) $pin->code));
    }

    public function test_employee_cannot_verify_the_domain(): void
    {
        $organization = Organization::factory()->unverified()->create();
        $user = User::factory()->create();
        OrganizationMember::factory()->employee()->create([
            "organization_id" => $organization->id,
            "user_id" => $user->id,
        ]);
        $this->issuePin($user);

        Sanctum::actingAs($user);

        $this->postJson("/api/v2/business/domain/verify", ["code" => "472913"])
            ->assertStatus(403);

        $this->assertNull($organization->refresh()->verified_at);
    }
}
