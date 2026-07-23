<?php

namespace Tests\Feature\Admin;

use App\Constants\Account\User\UserConstants;
use App\Models\TherapistApplication;
use App\Models\TherapistDocument;
use App\Models\User;
use App\Notifications\Therapist\TherapistApplicationApprovedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TherapistReviewTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(["role" => UserConstants::ADMIN]);
    }

    public function test_approve_creates_therapist_row_and_flips_role(): void
    {
        Notification::fake();
        $applicant = User::factory()->create();
        $application = TherapistApplication::factory()->submitted()->complete()
            ->create(["user_id" => $applicant->id]);

        $this->actingAs($this->admin())
            ->postJson("/admin/therapist-applications/{$application->id}/approve")
            ->assertStatus(200)
            ->assertJsonPath("data.status", "approved");

        $this->assertDatabaseHas("therapists", [
            "user_id" => $applicant->id,
            "credential_type" => $application->credential_type,
        ]);
        $this->assertSame(UserConstants::THERAPIST, $applicant->refresh()->role);
    }

    public function test_approve_sends_notification(): void
    {
        Notification::fake();
        $applicant = User::factory()->create();
        $application = TherapistApplication::factory()->submitted()->create(["user_id" => $applicant->id]);

        $this->actingAs($this->admin())
            ->postJson("/admin/therapist-applications/{$application->id}/approve")
            ->assertStatus(200);

        Notification::assertSentTo($applicant, TherapistApplicationApprovedNotification::class);
    }

    public function test_reject_persists_reason_and_allows_resubmission(): void
    {
        Notification::fake();
        $applicant = User::factory()->create();
        $application = TherapistApplication::factory()->submitted()->create(["user_id" => $applicant->id]);

        $this->actingAs($this->admin())
            ->postJson("/admin/therapist-applications/{$application->id}/reject", [
                "reason" => "Licence expired",
            ])->assertStatus(200);

        $this->assertDatabaseHas("therapist_applications", [
            "id" => $application->id,
            "status" => "rejected",
            "rejection_reason" => "Licence expired",
        ]);

        // The applicant may start a new draft after rejection.
        \Laravel\Sanctum\Sanctum::actingAs($applicant);
        $this->postJson("/api/v2/therapist/application/personal", [
            "credential_type" => "Clinical Psychologist",
            "years_experience" => 4,
        ])->assertStatus(200);
        $this->assertSame(2, TherapistApplication::where("user_id", $applicant->id)->count());
    }

    public function test_document_reject_sets_status_and_reason(): void
    {
        $document = TherapistDocument::factory()->create();

        $this->actingAs($this->admin())
            ->postJson("/admin/therapist-applications/documents/{$document->id}/verdict", [
                "status" => "rejected",
                "reason" => "Document is blurry",
            ])->assertStatus(200);

        $this->assertDatabaseHas("therapist_documents", [
            "id" => $document->id,
            "status" => "rejected",
            "rejection_reason" => "Document is blurry",
        ]);
    }

    public function test_non_admin_gets_403(): void
    {
        $application = TherapistApplication::factory()->submitted()->create();
        $regular = User::factory()->create();

        $this->actingAs($regular)
            ->postJson("/admin/therapist-applications/{$application->id}/approve")
            ->assertStatus(403);
        $this->actingAs($regular)
            ->getJson("/admin/therapist-applications")
            ->assertStatus(403);
    }
}
