<?php

namespace Tests\Feature\V2\Business;

use App\Constants\Business\OrganizationConstants;
use App\Constants\Therapist\TherapistConstants;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PostCategory;
use App\Models\Therapist;
use App\Models\TherapistApplication;
use App\Models\TherapistAvailability;
use App\Models\TherapistReview;
use App\Models\TherapistSpecialty;
use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The "View profile" detail endpoint (web §04b): it must surface the rich
 * fields from REAL data — focus areas, formats, next slot, anonymised reviews —
 * and return null (never invented values) for fields the schema doesn't capture.
 */
class AdminTherapistDetailTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(Organization $organization): User
    {
        $admin = User::factory()->create();
        OrganizationMember::factory()->admin()->create([
            "organization_id" => $organization->id,
            "user_id" => $admin->id,
        ]);
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function fullyOnboardedTherapist(): Therapist
    {
        $user = User::factory()->create(["first_name" => "Adaora", "last_name" => "Nwosu"]);
        $therapist = Therapist::factory()->create([
            "user_id" => $user->id,
            "session_formats" => ["video", "voice", "chat"],
            "years_experience" => 9,
        ]);

        $application = TherapistApplication::factory()->create([
            "user_id" => $user->id,
            "status" => TherapistConstants::STATUS_APPROVED,
        ]);
        foreach (["Anxiety", "CBT"] as $name) {
            $category = PostCategory::factory()->interestTopic()->create(["name" => $name]);
            TherapistSpecialty::create(["application_id" => $application->id, "category_id" => $category->id]);
        }

        // Availability tomorrow → a computable "next slot".
        TherapistAvailability::factory()->create([
            "user_id" => $user->id,
            "day_of_week" => strtolower(now()->addDay()->englishDayOfWeek),
            "start_time" => "09:00",
            "end_time" => "12:00",
        ]);

        TherapistReview::factory()->create([
            "therapist_id" => $therapist->id,
            "session_id" => TherapySession::factory()->completed()->create(["therapist_id" => $therapist->id])->id,
            "rating" => 5,
            "comment" => "Warm and practical.",
        ]);

        return $therapist;
    }

    public function test_detail_surfaces_focus_areas_formats_next_slot_and_reviews(): void
    {
        $org = Organization::factory()->create();
        $this->actingAdmin($org);
        $therapist = $this->fullyOnboardedTherapist();

        $data = $this->getJson("/api/v2/business/therapists/{$therapist->id}")
            ->assertStatus(200)
            ->json("data");

        $this->assertEqualsCanonicalizing(["Anxiety", "CBT"], $data["focus_areas"]);
        $this->assertSame("Video · Voice · Chat", $data["formats"]);
        $this->assertSame(9, $data["years_experience"]);
        $this->assertNotNull($data["next_slot"]);

        $this->assertCount(1, $data["reviews_list"]);
        $this->assertSame("Warm and practical.", $data["reviews_list"][0]["comment"]);
        $this->assertSame(100, collect($data["rating_breakdown"])->firstWhere("stars", "5")["pct"]);
    }

    public function test_detail_reviews_are_anonymised_and_schemaless_fields_are_null(): void
    {
        $org = Organization::factory()->create();
        $this->actingAdmin($org);
        $therapist = $this->fullyOnboardedTherapist();

        $data = $this->getJson("/api/v2/business/therapists/{$therapist->id}")
            ->assertStatus(200)
            ->json("data");

        // No reviewer identity ever leaves the server.
        foreach (["user_id", "user", "name", "email"] as $identity) {
            $this->assertArrayNotHasKey($identity, $data["reviews_list"][0]);
        }

        // Fields the therapists table does not carry are null, not fabricated.
        $this->assertNull($data["bio"]);
        $this->assertNull($data["languages"]);
        $this->assertNull($data["response_time"]);
    }

    public function test_unknown_therapist_is_not_found(): void
    {
        $org = Organization::factory()->create();
        $this->actingAdmin($org);

        $this->getJson("/api/v2/business/therapists/99999")->assertStatus(404);
    }
}
