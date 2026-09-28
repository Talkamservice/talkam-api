<?php

namespace Tests\Feature\V2\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_bio_over_300_chars_rejected(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/user/profile/update", ["bio" => str_repeat("a", 301)])
            ->assertStatus(422)->assertJson(["success" => false]);

        $this->postJson("/api/v2/user/profile/update", ["bio" => str_repeat("a", 300)])
            ->assertStatus(200);

        $this->assertSame(300, strlen($user->refresh()->bio));
    }

    public function test_email_field_ignored_and_unchanged(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $original_email = $user->email;

        $this->postJson("/api/v2/user/profile/update", [
            "email" => "new@example.com",
            "bio" => "hello",
        ])->assertStatus(200);

        $this->assertSame($original_email, $user->refresh()->email);
    }

    public function test_name_and_phone_update_persist(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/user/profile/update", [
            "name" => "Ada Chioma Obi",
            "phone_number" => "+2348012345678",
        ])->assertStatus(200);

        $this->assertDatabaseHas("users", [
            "id" => $user->id,
            "first_name" => "Ada",
            "middle_name" => "Chioma",
            "last_name" => "Obi",
            "phone_number" => "+2348012345678",
        ]);
    }

    public function test_v1_profile_update_route_unchanged(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // v1 rules: name accepted; no v2 bio cap applies there.
        $this->postJson("/api/v1/user/profile/update", [
            "name" => "Ngozi Eze",
        ])->assertStatus(200)->assertJson(["success" => true]);

        $this->assertDatabaseHas("users", ["id" => $user->id, "first_name" => "Ngozi"]);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson("/api/v2/user/profile/update", [])->assertStatus(401);
    }
}
