<?php

namespace Tests\Feature\V2\Business;

use App\Constants\Auth\PinConstants;
use App\Constants\Business\OrganizationConstants;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Pin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear("");
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            "company_name" => "Zenith Bank Nigeria",
            "work_email" => "adaeze.okonkwo@zenithbank.com",
            "industry" => "Banking & Finance",
            "headcount_band" => "100 - 300",
            "password" => "Passw0rd12!",
        ], $overrides);
    }

    public function test_signup_creates_organization_admin_membership_and_verification_pin(): void
    {
        $response = $this->postJson("/api/v2/business/register", $this->payload())
            ->assertStatus(200)
            ->assertJson(["success" => true]);

        $organization = Organization::first();
        $this->assertNotNull($organization);
        $this->assertSame("Zenith Bank Nigeria", $organization->name);
        $this->assertSame("zenithbank.com", $organization->domain);
        $this->assertSame(OrganizationConstants::STATUS_PENDING_VERIFICATION, $organization->status);
        $this->assertNull($organization->verified_at);

        $user = User::where("email", "adaeze.okonkwo@zenithbank.com")->first();
        $this->assertNotNull($user);
        $this->assertSame($user->id, $organization->created_by);

        $membership = OrganizationMember::where("user_id", $user->id)->first();
        $this->assertNotNull($membership);
        $this->assertSame(OrganizationConstants::ROLE_ADMIN, $membership->role);
        $this->assertSame(OrganizationConstants::MEMBER_ACTIVE, $membership->status);

        // A 6-digit verify_email pin was issued for the domain confirmation screen.
        $pin = Pin::where("user_id", $user->id)->where("type", PinConstants::TYPE_VERIFY_EMAIL)->first();
        $this->assertNotNull($pin);
        $this->assertSame(6, strlen((string) $pin->code));

        $this->assertNotEmpty($response->json("data.token"));
        $this->assertFalse($organization->isVerified());
    }

    public function test_signup_rejects_free_email_providers(): void
    {
        foreach (["adaeze@gmail.com", "adaeze@yahoo.com", "adaeze@outlook.com"] as $email) {
            $this->postJson("/api/v2/business/register", $this->payload(["work_email" => $email]))
                ->assertStatus(422)
                ->assertJson(["success" => false]);
        }

        $this->assertSame(0, Organization::count());
        $this->assertSame(0, User::count());
    }

    public function test_signup_rejects_a_domain_that_already_has_an_account(): void
    {
        Organization::factory()->create(["domain" => "zenithbank.com"]);

        $this->postJson("/api/v2/business/register", $this->payload())
            ->assertStatus(422)
            ->assertJson(["success" => false]);

        $this->assertSame(1, Organization::count());
    }

    public function test_signup_rejects_an_email_already_registered(): void
    {
        User::factory()->create(["email" => "adaeze.okonkwo@zenithbank.com"]);

        $this->postJson("/api/v2/business/register", $this->payload())
            ->assertStatus(422);

        $this->assertSame(0, Organization::count());
    }

    public function test_signup_rejects_a_weak_password(): void
    {
        $this->postJson("/api/v2/business/register", $this->payload(["password" => "password"]))
            ->assertStatus(422);

        $this->assertSame(0, Organization::count());
    }

    public function test_signup_derives_a_unique_username_and_never_asks_for_one(): void
    {
        User::factory()->create(["username" => "adaeze.okonkwo"]);

        $this->postJson("/api/v2/business/register", $this->payload())->assertStatus(200);

        $user = User::where("email", "adaeze.okonkwo@zenithbank.com")->first();
        $this->assertNotNull($user->username);
        $this->assertNotSame("adaeze.okonkwo", $user->username);
    }

    public function test_signup_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson("/api/v2/business/register", $this->payload([
                "work_email" => "admin{$i}@company{$i}.com",
            ]))->assertStatus(200);
        }

        $this->postJson("/api/v2/business/register", $this->payload([
            "work_email" => "admin6@company6.com",
        ]))->assertStatus(429);
    }
}
