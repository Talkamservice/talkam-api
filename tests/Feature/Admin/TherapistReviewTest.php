<?php

namespace Tests\Feature\Admin;

use App\Constants\Account\User\UserConstants;
use App\Constants\Business\OrganizationConstants as OC;
use App\Models\NotificationPreference;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\TherapistApplication;
use App\Models\TherapistDocument;
use App\Models\User;
use App\Notifications\Business\NewTherapistAnnouncementNotification;
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

    private function businessAdmin(bool $subscribed): User
    {
        $orgAdmin = User::factory()->create();
        $org = Organization::create([
            "name" => "Co", "slug" => "co-" . uniqid(), "domain" => "co" . uniqid() . ".ng",
            "status" => OC::STATUS_ACTIVE, "verified_at" => now(),
        ]);
        OrganizationMember::create([
            "organization_id" => $org->id, "user_id" => $orgAdmin->id,
            "role" => OC::ROLE_ADMIN, "status" => OC::MEMBER_ACTIVE, "activated_at" => now(),
        ]);

        if ($subscribed) {
            NotificationPreference::create(["user_id" => $orgAdmin->id, "new_therapist_announcements" => 1]);
        }

        return $orgAdmin;
    }

    public function test_approve_announces_a_new_therapist_platform_wide_to_subscribed_admins(): void
    {
        Notification::fake();
        $subscribed = $this->businessAdmin(subscribed: true);
        $unsubscribed = $this->businessAdmin(subscribed: false); // never opted in -> default OFF

        $applicant = User::factory()->create();
        $application = TherapistApplication::factory()->submitted()->create(["user_id" => $applicant->id]);

        $this->actingAs($this->admin())
            ->postJson("/admin/therapist-applications/{$application->id}/approve")
            ->assertStatus(200);

        Notification::assertSentTo($subscribed, NewTherapistAnnouncementNotification::class);
        Notification::assertNotSentTo($unsubscribed, NewTherapistAnnouncementNotification::class);
    }

    public function test_approve_does_not_re_announce_an_existing_therapist(): void
    {
        Notification::fake();
        $subscribed = $this->businessAdmin(subscribed: true);
        $applicant = User::factory()->create();

        // Already an approved, existing therapist (e.g. re-approved after a rate-change resubmission).
        \App\Models\Therapist::factory()->create(["user_id" => $applicant->id]);
        $application = TherapistApplication::factory()->submitted()->create(["user_id" => $applicant->id]);

        $this->actingAs($this->admin())
            ->postJson("/admin/therapist-applications/{$application->id}/approve")
            ->assertStatus(200);

        Notification::assertNotSentTo($subscribed, NewTherapistAnnouncementNotification::class);
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
