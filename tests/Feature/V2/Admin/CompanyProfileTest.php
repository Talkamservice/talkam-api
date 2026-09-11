<?php

namespace Tests\Feature\V2\Admin;

use App\Constants\Business\OrganizationConstants as OC;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Company profile settings: name/industry are editable; domain and
 * hr_contact_email are not (support-only, like the domain); the logo is
 * replaced via a real image upload.
 */
class CompanyProfileTest extends TestCase
{
    use RefreshDatabase;

    private function orgWithAdmin(): array
    {
        $admin = User::factory()->create();
        $org = Organization::create([
            "name" => "Meridian Health",
            "slug" => "meridian-" . uniqid(),
            "domain" => "meridian" . uniqid() . ".ng",
            "status" => OC::STATUS_ACTIVE,
            "hr_contact_email" => "hr@meridian.ng",
            "verified_at" => now(),
            "created_by" => $admin->id,
        ]);
        OrganizationMember::create([
            "organization_id" => $org->id,
            "user_id" => $admin->id,
            "role" => OC::ROLE_ADMIN,
            "status" => OC::MEMBER_ACTIVE,
            "activated_at" => now(),
        ]);

        return [$org, $admin];
    }

    public function test_name_and_industry_are_editable(): void
    {
        [$org, $admin] = $this->orgWithAdmin();
        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/v2/business/organization/profile", [
            "name" => "Meridian Wellness",
            "industry" => "Technology",
        ]);

        $response->assertOk();
        $this->assertSame("Meridian Wellness", $org->refresh()->name);
        $this->assertSame("Technology", $org->industry);
    }

    public function test_domain_is_not_editable_but_hr_contact_email_is(): void
    {
        [$org, $admin] = $this->orgWithAdmin();
        Sanctum::actingAs($admin);
        $original_domain = $org->domain;

        $response = $this->postJson("/api/v2/business/organization/profile", [
            "domain" => "hijacked.ng",
            "hr_contact_email" => "newhr@meridian.ng",
        ]);

        $response->assertOk();
        $org->refresh();
        $this->assertSame($original_domain, $org->domain);
        $this->assertSame("newhr@meridian.ng", $org->hr_contact_email);
    }

    public function test_admin_can_upload_a_logo(): void
    {
        [$org, $admin] = $this->orgWithAdmin();
        Sanctum::actingAs($admin);

        $file = UploadedFile::fake()->image("logo.png", 300, 300);

        $response = $this->post("/api/v2/business/organization/logo", ["logo" => $file], [
            "Accept" => "application/json",
        ]);

        $response->assertOk();
        $org->refresh();
        $this->assertNotEmpty($org->logo);
        $this->assertSame($org->logo, $response->json("data.organization.logo"));
    }

    public function test_logo_upload_rejects_a_too_small_image(): void
    {
        [, $admin] = $this->orgWithAdmin();
        Sanctum::actingAs($admin);

        $file = UploadedFile::fake()->image("tiny.png", 50, 50);

        $this->post("/api/v2/business/organization/logo", ["logo" => $file], [
            "Accept" => "application/json",
        ])->assertStatus(422);
    }

    public function test_logo_upload_requires_an_admin(): void
    {
        [$org] = $this->orgWithAdmin();
        $employee = User::factory()->create();
        OrganizationMember::create([
            "organization_id" => $org->id,
            "user_id" => $employee->id,
            "role" => OC::ROLE_EMPLOYEE,
            "status" => OC::MEMBER_ACTIVE,
            "activated_at" => now(),
        ]);
        Sanctum::actingAs($employee);

        $file = UploadedFile::fake()->image("logo.png", 300, 300);

        $this->post("/api/v2/business/organization/logo", ["logo" => $file], [
            "Accept" => "application/json",
        ])->assertForbidden();
    }
}
