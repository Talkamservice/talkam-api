<?php

namespace Tests\Feature\V2\User;

use App\Models\User;
use App\Models\UserReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_report_creates_row(): void
    {
        $reporter = User::factory()->create();
        $target = User::factory()->create();
        Sanctum::actingAs($reporter);

        $this->postJson("/api/v2/user/user-reports", [
            "reported_user_id" => $target->id,
            "reason" => "Abusive messages during a session",
        ])->assertStatus(200)->assertJson(["success" => true]);

        $this->assertDatabaseHas("user_reports", [
            "reporter_id" => $reporter->id,
            "reported_user_id" => $target->id,
            "status" => "Pending",
        ]);
    }

    public function test_missing_reason_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $target = User::factory()->create();

        $this->postJson("/api/v2/user/user-reports", ["reported_user_id" => $target->id])
            ->assertStatus(422);
    }

    public function test_unknown_target_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v2/user/user-reports", [
            "reported_user_id" => 999999,
            "reason" => "Spam",
        ])->assertStatus(422);
    }

    public function test_self_report_rejected(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/user/user-reports", [
            "reported_user_id" => $user->id,
            "reason" => "Testing",
        ])->assertStatus(422);
    }

    public function test_duplicate_open_report_rejected(): void
    {
        $reporter = User::factory()->create();
        $target = User::factory()->create();
        Sanctum::actingAs($reporter);

        $this->postJson("/api/v2/user/user-reports", [
            "reported_user_id" => $target->id,
            "reason" => "First report",
        ])->assertStatus(200);

        $this->postJson("/api/v2/user/user-reports", [
            "reported_user_id" => $target->id,
            "reason" => "Second report",
        ])->assertStatus(422);

        $this->assertSame(1, UserReport::count());
    }

    public function test_guest_unauthorized(): void
    {
        $this->postJson("/api/v2/user/user-reports", [])->assertStatus(401);
    }
}
