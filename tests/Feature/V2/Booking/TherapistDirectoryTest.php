<?php

namespace Tests\Feature\V2\Booking;

use App\Constants\Therapist\TherapistConstants;
use App\Models\PostCategory;
use App\Models\Therapist;
use App\Models\TherapistApplication;
use App\Models\TherapistReview;
use App\Models\TherapistSpecialty;
use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TherapistDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_verified_therapists_with_card_fields(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Therapist::factory()->create();

        $this->getJson("/api/v2/user/therapists")
            ->assertStatus(200)
            ->assertJson(["success" => true, "code" => 200])
            ->assertJsonStructure(["data" => ["data" => [
                ["id", "name", "is_verified", "credential_type", "session_rate", "rating", "reviews_count", "specialties", "next_slot"],
            ]]]);
    }

    public function test_excludes_unverified_business_therapists(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $verified = Therapist::factory()->create();
        $unverified = Therapist::factory()->create(["verified_at" => null]);

        // Not listed in the open consumer directory...
        $ids = collect($this->getJson("/api/v2/user/therapists")->json("data.data"))->pluck("id");
        $this->assertTrue($ids->contains($verified->id));
        $this->assertFalse($ids->contains($unverified->id));

        // ...and not reachable directly for a profile or booking slots.
        $this->getJson("/api/v2/user/therapists/{$unverified->id}")->assertStatus(404);
        $this->getJson("/api/v2/user/therapists/{$unverified->id}/slots?date=2026-09-01")->assertStatus(404);
    }

    public function test_search_filters_by_name(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Therapist::factory()->create([
            "user_id" => User::factory()->create(["first_name" => "Adaora", "last_name" => "Nwosu"])->id,
        ]);

        $this->assertNotEmpty($this->getJson("/api/v2/user/therapists?search=Adaora")->json("data.data"));
        $this->assertEmpty($this->getJson("/api/v2/user/therapists?search=Zzzz")->json("data.data"));
    }

    public function test_specialty_filter_limits_results(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $topic = PostCategory::factory()->interestTopic()->create();

        $matching_user = User::factory()->create();
        $matching = Therapist::factory()->create(["user_id" => $matching_user->id]);
        $application = TherapistApplication::factory()->create([
            "user_id" => $matching_user->id,
            "status" => TherapistConstants::STATUS_APPROVED,
        ]);
        TherapistSpecialty::create(["application_id" => $application->id, "category_id" => $topic->id]);

        $other = Therapist::factory()->create();

        $ids = collect($this->getJson("/api/v2/user/therapists?specialty_id={$topic->id}")->json("data.data"))
            ->pluck("id");
        $this->assertTrue($ids->contains($matching->id));
        $this->assertFalse($ids->contains($other->id));
    }

    public function test_sorts_by_rating_and_price(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $cheap_low_rated = Therapist::factory()->create(["session_rate" => 15000]);
        $pricey_high_rated = Therapist::factory()->create(["session_rate" => 19000]);

        TherapistReview::factory()->create([
            "therapist_id" => $pricey_high_rated->id,
            "session_id" => TherapySession::factory()->completed()->create(["therapist_id" => $pricey_high_rated->id])->id,
            "rating" => 5,
        ]);
        TherapistReview::factory()->create([
            "therapist_id" => $cheap_low_rated->id,
            "session_id" => TherapySession::factory()->completed()->create(["therapist_id" => $cheap_low_rated->id])->id,
            "rating" => 2,
        ]);

        $by_rating = collect($this->getJson("/api/v2/user/therapists?sort=rating")->json("data.data"))->pluck("id");
        $this->assertTrue($by_rating->search($pricey_high_rated->id) < $by_rating->search($cheap_low_rated->id));

        $by_price = collect($this->getJson("/api/v2/user/therapists?sort=price")->json("data.data"))->pluck("id");
        $this->assertTrue($by_price->search($cheap_low_rated->id) < $by_price->search($pricey_high_rated->id));
    }

    public function test_rating_aggregates_match_review_rows(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $therapist = Therapist::factory()->create();
        foreach ([5, 4] as $rating) {
            TherapistReview::factory()->create([
                "therapist_id" => $therapist->id,
                "session_id" => TherapySession::factory()->completed()->create(["therapist_id" => $therapist->id])->id,
                "rating" => $rating,
            ]);
        }

        $card = collect($this->getJson("/api/v2/user/therapists")->json("data.data"))
            ->firstWhere("id", $therapist->id);

        $this->assertEquals(4.5, $card["rating"]);
        $this->assertSame(2, $card["reviews_count"]);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson("/api/v2/user/therapists")->assertStatus(401);
    }
}
