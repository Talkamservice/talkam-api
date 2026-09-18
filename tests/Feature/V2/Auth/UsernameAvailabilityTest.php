<?php

namespace Tests\Feature\V2\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsernameAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_availability_check_is_public(): void
    {
        $this->getJson("/api/v2/auth/username/available?username=some_name")
            ->assertStatus(200)
            ->assertJson(["success" => true, "code" => 200])
            ->assertJsonStructure(["message", "data" => ["username", "available"], "success", "code"]);
    }

    public function test_taken_username_reports_unavailable(): void
    {
        User::factory()->create(["username" => "taken_name"]);

        $this->getJson("/api/v2/auth/username/available?username=taken_name")
            ->assertStatus(200)
            ->assertJsonPath("data.available", false);
    }

    public function test_free_username_reports_available(): void
    {
        $this->getJson("/api/v2/auth/username/available?username=free_name")
            ->assertStatus(200)
            ->assertJsonPath("data.available", true);
    }

    public function test_missing_username_is_rejected(): void
    {
        $this->getJson("/api/v2/auth/username/available")
            ->assertStatus(422)
            ->assertJson(["success" => false]);
    }

    public function test_endpoint_is_throttled(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->getJson("/api/v2/auth/username/available?username=name_$i");
        }

        $this->getJson("/api/v2/auth/username/available?username=final_name")
            ->assertStatus(429);
    }
}
