<?php

namespace Tests\Feature\V2\Therapist;

use App\Models\PostCategory;
use App\Models\TherapistSpecialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SpecialtiesStepTest extends TestCase
{
    use RefreshDatabase;

    public function test_saves_bio_and_specialty_pivots(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $topics = PostCategory::factory()->count(2)->interestTopic()->create();

        $this->postJson("/api/v2/therapist/application/specialties", [
            "bio" => "Trauma-informed therapist with 8 years of practice.",
            "specialties" => $topics->pluck("id")->all(),
        ])->assertStatus(200)->assertJson(["success" => true]);

        $this->assertDatabaseHas("therapist_applications", [
            "user_id" => $user->id,
            "bio" => "Trauma-informed therapist with 8 years of practice.",
        ]);
        // Bio also serves the §05 search People surface.
        $this->assertDatabaseHas("users", [
            "id" => $user->id,
            "bio" => "Trauma-informed therapist with 8 years of practice.",
        ]);
        $this->assertSame(2, TherapistSpecialty::count());
    }

    public function test_rejects_non_interest_topic_specialty_id(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $plain = PostCategory::factory()->create();

        $this->postJson("/api/v2/therapist/application/specialties", [
            "bio" => "A bio",
            "specialties" => [$plain->id],
        ])->assertStatus(422)->assertJson(["success" => false]);

        $this->assertSame(0, TherapistSpecialty::count());
    }

    public function test_resubmit_creates_no_duplicate_pivots(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $topics = PostCategory::factory()->count(2)->interestTopic()->create();
        $payload = [
            "bio" => "A bio",
            "specialties" => $topics->pluck("id")->all(),
        ];

        $this->postJson("/api/v2/therapist/application/specialties", $payload)->assertStatus(200);
        $this->postJson("/api/v2/therapist/application/specialties", $payload)->assertStatus(200);

        $this->assertSame(2, TherapistSpecialty::count());
    }

    public function test_requires_authentication(): void
    {
        $this->postJson("/api/v2/therapist/application/specialties", [])->assertStatus(401);
    }
}
