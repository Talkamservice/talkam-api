<?php

namespace Tests\Feature\V2\Booking;

use App\Models\TherapistReview;
use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TherapistReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_review_completed_session_once(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $session = TherapySession::factory()->completed()->create(["user_id" => $user->id]);

        $this->postJson("/api/v2/user/bookings/{$session->id}/review", [
            "rating" => 5,
            "comment" => "Very helpful session.",
        ])->assertStatus(200)->assertJson(["success" => true]);

        $this->assertDatabaseHas("therapist_reviews", [
            "session_id" => $session->id,
            "rating" => 5,
        ]);
    }

    public function test_non_completed_statuses_rejected(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        foreach (["pending_payment", "confirmed", "cancelled"] as $status) {
            $session = TherapySession::factory()->create([
                "user_id" => $user->id,
                "status" => $status,
            ]);

            $this->postJson("/api/v2/user/bookings/{$session->id}/review", ["rating" => 4])
                ->assertStatus(400)->assertJson(["success" => false]);
        }

        $this->assertSame(0, TherapistReview::count());
    }

    public function test_duplicate_review_rejected(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $session = TherapySession::factory()->completed()->create(["user_id" => $user->id]);

        $this->postJson("/api/v2/user/bookings/{$session->id}/review", ["rating" => 5])->assertStatus(200);
        $this->postJson("/api/v2/user/bookings/{$session->id}/review", ["rating" => 3])->assertStatus(400);

        $this->assertSame(1, TherapistReview::count());
    }

    public function test_non_owner_rejected(): void
    {
        $session = TherapySession::factory()->completed()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v2/user/bookings/{$session->id}/review", ["rating" => 5])
            ->assertStatus(404);

        $this->assertSame(0, TherapistReview::count());
    }

    public function test_rating_bounds_validated(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $session = TherapySession::factory()->completed()->create(["user_id" => $user->id]);

        foreach ([0, 6, null] as $bad) {
            $this->postJson("/api/v2/user/bookings/{$session->id}/review", ["rating" => $bad])
                ->assertStatus(422);
        }
    }

    public function test_reviews_list_paginated_anonymised_with_histogram(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $session = TherapySession::factory()->completed()->create();
        $therapist_id = $session->therapist_id;
        TherapistReview::factory()->create([
            "session_id" => $session->id,
            "therapist_id" => $therapist_id,
            "rating" => 5,
        ]);
        $other_session = TherapySession::factory()->completed()->create(["therapist_id" => $therapist_id]);
        TherapistReview::factory()->create([
            "session_id" => $other_session->id,
            "therapist_id" => $therapist_id,
            "rating" => 4,
        ]);

        $response = $this->getJson("/api/v2/user/therapists/{$therapist_id}/reviews")
            ->assertStatus(200);

        $data = $response->json("data");
        $this->assertSame(1, $data["histogram"]["5"] ?? $data["histogram"][5]);
        $this->assertSame(1, $data["histogram"]["4"] ?? $data["histogram"][4]);

        foreach ($data["data"] as $review) {
            $this->assertSame("Anonymous", $review["author"]);
            $this->assertArrayNotHasKey("user_id", $review);
            $this->assertArrayNotHasKey("name", $review);
            $this->assertArrayNotHasKey("username", $review);
        }
    }
}
