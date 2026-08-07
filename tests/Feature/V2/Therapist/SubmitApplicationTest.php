<?php

namespace Tests\Feature\V2\Therapist;

use App\Constants\Therapist\TherapistConstants;
use App\Models\PostCategory;
use App\Models\TherapistApplication;
use App\Models\TherapistAvailability;
use App\Models\TherapistDocument;
use App\Models\TherapistPayoutAccount;
use App\Models\TherapistSpecialty;
use App\Models\User;
use App\Notifications\Therapist\TherapistApplicationSubmittedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubmitApplicationTest extends TestCase
{
    use RefreshDatabase;

    private function completeApplication(User $user, array $except = []): TherapistApplication
    {
        $application = TherapistApplication::factory()->complete()->create(["user_id" => $user->id]);

        if (!in_array("documents", $except)) {
            foreach (TherapistConstants::DOCUMENT_TYPES as $type) {
                TherapistDocument::factory()->type($type)->create(["application_id" => $application->id]);
            }
        }
        if (!in_array("specialties", $except)) {
            $topic = PostCategory::factory()->interestTopic()->create();
            TherapistSpecialty::create(["application_id" => $application->id, "category_id" => $topic->id]);
        }
        if (!in_array("availability", $except)) {
            TherapistAvailability::create([
                "user_id" => $user->id,
                "day_of_week" => "monday",
                "start_time" => "09:00",
                "end_time" => "17:00",
                "active" => true,
            ]);
        }
        if (!in_array("payout", $except)) {
            TherapistPayoutAccount::create([
                "user_id" => $user->id,
                "bank_code" => "058",
                "bank_name" => "GTBank",
                "account_number" => "0123456789",
                "account_name" => "ADA OBI",
                "verified_at" => now(),
            ]);
        }

        return $application;
    }

    public function test_submit_with_all_steps_complete_sets_submitted(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->completeApplication($user);

        $this->postJson("/api/v2/therapist/application/submit")
            ->assertStatus(200)
            ->assertJsonPath("data.status", "submitted");

        $application = TherapistApplication::where("user_id", $user->id)->first();
        $this->assertNotNull($application->submitted_at);
        Notification::assertSentTo($user, TherapistApplicationSubmittedNotification::class);
    }

    public function test_submit_with_missing_step_fails_and_stays_draft(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        // All documents missing (including the headshot).
        $this->completeApplication($user, except: ["documents"]);

        $response = $this->postJson("/api/v2/therapist/application/submit")
            ->assertStatus(422)->assertJson(["success" => false]);

        $this->assertArrayHasKey("documents", $response->json("errors"));
        $this->assertSame("draft", TherapistApplication::where("user_id", $user->id)->first()->status);
        Notification::assertNothingSent();
    }

    public function test_second_active_application_blocked(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        TherapistApplication::factory()->submitted()->create(["user_id" => $user->id]);

        $this->postJson("/api/v2/therapist/application/personal", [
            "credential_type" => "Clinical Psychologist",
            "years_experience" => 4,
        ])->assertStatus(400)->assertJson(["success" => false]);

        $this->assertSame(1, TherapistApplication::where("user_id", $user->id)->count());
    }

    public function test_requires_authentication(): void
    {
        $this->postJson("/api/v2/therapist/application/submit")->assertStatus(401);
    }
}
