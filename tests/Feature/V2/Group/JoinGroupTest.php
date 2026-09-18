<?php

namespace Tests\Feature\V2\Group;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JoinGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_join_creates_member_row_with_member_role(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $group = Group::factory()->create();

        $this->postJson("/api/v2/user/group-members", [
            "group_id" => $group->id,
            "user_id" => $user->id,
        ])->assertStatus(200)->assertJson(["success" => true]);

        $this->assertDatabaseHas("group_members", [
            "group_id" => $group->id,
            "user_id" => $user->id,
            "role" => "Member",
        ]);
    }

    public function test_second_join_is_idempotent(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $group = Group::factory()->create();

        $payload = ["group_id" => $group->id, "user_id" => $user->id];
        $this->postJson("/api/v2/user/group-members", $payload)->assertStatus(200);
        $this->postJson("/api/v2/user/group-members", $payload)->assertStatus(200);

        $this->assertSame(1, GroupMember::where($payload)->count());
    }

    public function test_requires_authentication(): void
    {
        $this->postJson("/api/v2/user/group-members", [])->assertStatus(401);
    }
}
