<?php

namespace Tests\Feature\V2\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeleteAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_creates_deactivation_record(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/user/profile/delete-account", [
            "reason" => "No longer needed",
        ]);

        // user_id nulls out when the user row is force-deleted (FK nullOnDelete).
        $this->assertDatabaseHas("account_deactivations", [
            "email" => $user->email,
        ]);
        $this->assertDatabaseMissing("users", ["id" => $user->id]);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson("/api/v2/user/profile/delete-account", [])->assertStatus(401);
    }
}
