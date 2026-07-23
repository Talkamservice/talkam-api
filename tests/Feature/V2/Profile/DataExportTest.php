<?php

namespace Tests\Feature\V2\Profile;

use App\Jobs\ProcessDataExportJob;
use App\Models\DataExportRequest;
use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DataExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_creates_row_and_queues_job(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/user/data-export")
            ->assertStatus(200)->assertJsonPath("data.status", "Pending");

        $this->assertDatabaseHas("data_export_requests", [
            "user_id" => $user->id,
            "status" => "Pending",
        ]);
        Queue::assertPushed(ProcessDataExportJob::class);
    }

    public function test_latest_returns_signed_link_with_config_expiry(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $file = File::create([
            "file_group" => "data-exports",
            "path" => "app/media/data-exports/export.json",
        ]);
        DataExportRequest::create([
            "user_id" => $user->id,
            "status" => "Completed",
            "file_id" => $file->id,
            "expires_at" => now()->addDays(config("v2.data_export.expiry_days")),
        ]);

        $data = $this->getJson("/api/v2/user/data-export/latest")->assertStatus(200)->json("data");

        $this->assertSame("Completed", $data["status"]);
        $this->assertNotEmpty($data["download_url"]);
    }

    public function test_expired_link_not_served(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $file = File::create([
            "file_group" => "data-exports",
            "path" => "app/media/data-exports/export.json",
        ]);
        DataExportRequest::create([
            "user_id" => $user->id,
            "status" => "Completed",
            "file_id" => $file->id,
            "expires_at" => now()->addDays(config("v2.data_export.expiry_days")),
        ]);

        $this->travel(config("v2.data_export.expiry_days") + 1)->days();

        $this->getJson("/api/v2/user/data-export/latest")
            ->assertStatus(200)
            ->assertJsonPath("data.download_url", null);
    }
}
