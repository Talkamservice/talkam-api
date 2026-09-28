<?php

namespace Tests\Feature\V2\Booking;

use App\Models\Therapist;
use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TherapistProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_profile_with_stats_formats_and_fee(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $user = User::factory()->create(["bio" => "CBT specialist"]);
        $therapist = Therapist::factory()->create([
            "user_id" => $user->id,
            "session_rate" => 18000,
        ]);
        TherapySession::factory()->completed()->count(2)->create(["therapist_id" => $therapist->id]);

        $response = $this->getJson("/api/v2/user/therapists/{$therapist->id}")
            ->assertStatus(200)
            ->assertJsonPath("data.bio", "CBT specialist")
            ->assertJsonPath("data.completed_sessions", 2)
            ->assertJsonPath("data.session_formats", ["video", "voice"]);

        $this->assertEquals(18000, (float) $response->json("data.session_rate"));
    }

    public function test_unknown_therapist_returns_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v2/user/therapists/99999")
            ->assertStatus(404)->assertJson(["success" => false]);
    }
}
