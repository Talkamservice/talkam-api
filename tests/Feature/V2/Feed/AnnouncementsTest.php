<?php

namespace Tests\Feature\V2\Feed;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnnouncementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_v2_announcements_returns_seeded_banner(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $announcement = Announcement::factory()->create([
            "title" => "You're not alone",
        ]);

        $response = $this->getJson("/api/v2/user/announcements")
            ->assertStatus(200)
            ->assertJson(["success" => true, "code" => 200]);

        $titles = collect($response->json("data"))->pluck("title");
        $this->assertTrue($titles->contains("You're not alone"));
    }
}
