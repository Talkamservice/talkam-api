<?php

namespace Tests\Feature\V2\Business;

use App\Models\Invitation;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvitationImportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Organization
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();

        OrganizationMember::factory()->admin()->create([
            "organization_id" => $organization->id,
            "user_id" => $user->id,
        ]);

        Sanctum::actingAs($user);

        return $organization;
    }

    private function csv(string $contents, string $name = "roster.csv"): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), "csv");
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, "text/csv", null, true);
    }

    public function test_a_valid_roster_parses_into_rows(): void
    {
        $this->admin();

        $file = $this->csv(<<<CSV
        email,role,department
        chidinma.eze@zenithbank.com,employee,Technology
        tunde.balogun@zenithbank.com,employee,Finance
        dr.ngozi.uba@practice.ng,therapist,Clinical
        CSV);

        $this->post("/api/v2/business/invitations/import", ["file" => $file], ["Accept" => "application/json"])
            ->assertStatus(200)
            ->assertJsonPath("data.valid_count", 3)
            ->assertJsonPath("data.invalid_count", 0)
            ->assertJsonPath("data.filename", "roster.csv")
            ->assertJsonPath("data.rows.0.email", "chidinma.eze@zenithbank.com")
            ->assertJsonPath("data.rows.2.role", "therapist");

        // Parsing creates nothing — the admin reviews before sending.
        $this->assertSame(0, Invitation::count());
    }

    public function test_a_pdf_is_rejected_with_the_offending_filename(): void
    {
        $this->admin();

        $file = UploadedFile::fake()->create("roster.pdf", 20, "application/pdf");

        $this->post("/api/v2/business/invitations/import", ["file" => $file], ["Accept" => "application/json"])
            ->assertStatus(422)
            ->assertJsonPath("errors.file.0", "Only .csv files are supported");
    }

    public function test_an_oversized_file_is_rejected(): void
    {
        $this->admin();

        $file = UploadedFile::fake()->create("roster.csv", 6000, "text/csv");

        $this->post("/api/v2/business/invitations/import", ["file" => $file], ["Accept" => "application/json"])
            ->assertStatus(422)
            ->assertJsonPath("errors.file.0", "File must be 5MB or smaller");
    }

    public function test_bad_rows_come_back_flagged_rather_than_failing_the_upload(): void
    {
        $this->admin();

        $file = $this->csv(<<<CSV
        email,role,department
        good@zenithbank.com,employee,Technology
        not-an-email,employee,Finance
        someone@zenithbank.com,manager,Operations
        another@zenithbank.com,therapist,Clinical
        CSV);

        $response = $this->post("/api/v2/business/invitations/import", ["file" => $file], ["Accept" => "application/json"])
            ->assertStatus(200)
            ->assertJsonPath("data.valid_count", 2)
            ->assertJsonPath("data.invalid_count", 2);

        $invalid = $response->json("data.invalid_rows");
        $this->assertSame("Not a valid email address", $invalid[0]["errors"][0]);
        $this->assertSame("Role must be employee or therapist", $invalid[1]["errors"][0]);
    }

    public function test_column_order_and_casing_do_not_matter(): void
    {
        $this->admin();

        $file = $this->csv(<<<CSV
        Department,Email,Role
        Technology,chidinma.eze@zenithbank.com,EMPLOYEE
        CSV);

        $this->post("/api/v2/business/invitations/import", ["file" => $file], ["Accept" => "application/json"])
            ->assertStatus(200)
            ->assertJsonPath("data.valid_count", 1)
            ->assertJsonPath("data.rows.0.department", "Technology")
            ->assertJsonPath("data.rows.0.role", "employee");
    }

    public function test_blank_lines_are_skipped(): void
    {
        $this->admin();

        $file = $this->csv("email,role,department\ngood@zenithbank.com,employee,Tech\n\n\n");

        $this->post("/api/v2/business/invitations/import", ["file" => $file], ["Accept" => "application/json"])
            ->assertStatus(200)
            ->assertJsonPath("data.valid_count", 1)
            ->assertJsonPath("data.invalid_count", 0);
    }

    public function test_a_missing_file_is_rejected(): void
    {
        $this->admin();

        $this->post("/api/v2/business/invitations/import", [], ["Accept" => "application/json"])
            ->assertStatus(422);
    }

    public function test_an_employee_cannot_import_a_roster(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        OrganizationMember::factory()->employee()->create([
            "organization_id" => $organization->id,
            "user_id" => $user->id,
        ]);
        Sanctum::actingAs($user);

        $file = $this->csv("email,role,department\ngood@zenithbank.com,employee,Tech");

        $this->post("/api/v2/business/invitations/import", ["file" => $file], ["Accept" => "application/json"])
            ->assertStatus(403);
    }
}
