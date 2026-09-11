<?php

namespace Tests\Feature\V2\Therapist;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AvailabilityStepTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            "session_duration" => config("therapist.session_durations")[0],
            "buffer_minutes" => config("therapist.buffers")[1],
            "days" => [
                ["day_of_week" => "monday", "start_time" => "09:00", "end_time" => "17:00", "active" => true],
                ["day_of_week" => "sunday", "start_time" => null, "end_time" => null, "active" => false],
            ],
        ], $overrides);
    }

    public function test_saves_per_day_rows_with_active_toggle(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/therapist/application/availability", $this->payload())
            ->assertStatus(200)->assertJson(["success" => true]);

        $this->assertDatabaseHas("therapist_availabilities", [
            "user_id" => $user->id,
            "day_of_week" => "monday",
            "active" => true,
        ]);
        $this->assertDatabaseHas("therapist_availabilities", [
            "user_id" => $user->id,
            "day_of_week" => "sunday",
            "active" => false,
        ]);
    }

    public function test_rejects_duration_preset_outside_config(): void
    {
        Sanctum::actingAs(User::factory()->create());
        config(["therapist.session_durations" => [50, 75, 80]]);

        $this->postJson("/api/v2/therapist/application/availability", $this->payload([
            "session_duration" => 45,
        ]))->assertStatus(422)->assertJson(["success" => false]);

        // Config-driven: a value inside the overridden list passes.
        $this->postJson("/api/v2/therapist/application/availability", $this->payload([
            "session_duration" => 75,
        ]))->assertStatus(200);
    }

    public function test_rejects_buffer_outside_config(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v2/therapist/application/availability", $this->payload([
            "buffer_minutes" => 45,
        ]))->assertStatus(422)->assertJson(["success" => false]);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson("/api/v2/therapist/application/availability", [])->assertStatus(401);
    }
}
