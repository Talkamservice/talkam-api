<?php

namespace Tests\Feature\V2\TherapistProfile;

use App\Constants\General\StatusConstants;
use App\Models\Therapist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TherapistProfileStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_deactivate_pulls_therapist_out_of_the_directory(): void
    {
        $therapist = Therapist::factory()->create();
        Sanctum::actingAs($therapist->user);

        $this->getJson("/api/v2/user/therapists")
            ->assertJson(fn ($json) => $json->has("data.data", 1)->etc());

        $this->postJson("/api/v2/therapist/profile/deactivate")
            ->assertStatus(200)
            ->assertJson(["success" => true, "data" => ["status" => StatusConstants::INACTIVE]]);

        $this->assertDatabaseHas("therapists", [
            "id" => $therapist->id,
            "status" => StatusConstants::INACTIVE,
        ]);

        $ids = collect($this->getJson("/api/v2/user/therapists")->json("data.data"))->pluck("id");
        $this->assertFalse($ids->contains($therapist->id));
    }

    public function test_reactivate_restores_directory_visibility(): void
    {
        $therapist = Therapist::factory()->create(["status" => StatusConstants::INACTIVE]);
        Sanctum::actingAs($therapist->user);

        $this->postJson("/api/v2/therapist/profile/reactivate")
            ->assertStatus(200)
            ->assertJson(["success" => true, "data" => ["status" => StatusConstants::ACTIVE]]);

        $this->assertDatabaseHas("therapists", [
            "id" => $therapist->id,
            "status" => StatusConstants::ACTIVE,
        ]);

        $ids = collect($this->getJson("/api/v2/user/therapists")->json("data.data"))->pluck("id");
        $this->assertTrue($ids->contains($therapist->id));
    }

    public function test_profile_self_view_exposes_status(): void
    {
        $therapist = Therapist::factory()->create();
        Sanctum::actingAs($therapist->user);

        $this->getJson("/api/v2/therapist/profile")
            ->assertStatus(200)
            ->assertJson(["data" => ["status" => StatusConstants::ACTIVE]]);
    }

    public function test_plain_user_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/v2/therapist/profile/deactivate")->assertStatus(403);
        $this->postJson("/api/v2/therapist/profile/reactivate")->assertStatus(403);
    }

    public function test_guest_gets_401(): void
    {
        $this->postJson("/api/v2/therapist/profile/deactivate")->assertStatus(401);
        $this->postJson("/api/v2/therapist/profile/reactivate")->assertStatus(401);
    }
}
