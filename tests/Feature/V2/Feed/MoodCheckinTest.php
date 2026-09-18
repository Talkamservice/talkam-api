<?php

namespace Tests\Feature\V2\Feed;

use App\Models\MoodCheckin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MoodCheckinTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkin_stores_mood_for_today(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/user/mood-checkins", ["mood" => 4])
            ->assertStatus(200)->assertJson(["success" => true]);

        $this->assertDatabaseHas("mood_checkins", [
            "user_id" => $user->id,
            "mood" => 4,
            "checked_in_on" => now()->toDateString(),
        ]);
    }

    public function test_mood_must_be_between_one_and_five(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        foreach ([0, 6, "happy"] as $bad_mood) {
            $this->postJson("/api/v2/user/mood-checkins", ["mood" => $bad_mood])
                ->assertStatus(422)->assertJson(["success" => false]);
        }

        $this->assertSame(0, MoodCheckin::where("user_id", $user->id)->count());
    }

    public function test_same_day_repeat_checkin_updates_existing_row(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/user/mood-checkins", ["mood" => 2])->assertStatus(200);
        $this->postJson("/api/v2/user/mood-checkins", ["mood" => 5])->assertStatus(200);

        $rows = MoodCheckin::where("user_id", $user->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame(5, $rows->first()->mood);
    }

    public function test_next_day_checkin_creates_new_row(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/user/mood-checkins", ["mood" => 3])->assertStatus(200);

        $this->travel(1)->days();
        $this->postJson("/api/v2/user/mood-checkins", ["mood" => 4])->assertStatus(200);

        $this->assertSame(2, MoodCheckin::where("user_id", $user->id)->count());
    }

    public function test_another_users_same_day_checkin_is_unaffected(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        MoodCheckin::factory()->create(["user_id" => $other->id, "mood" => 1]);

        Sanctum::actingAs($user);
        $this->postJson("/api/v2/user/mood-checkins", ["mood" => 5])->assertStatus(200);

        $this->assertSame(1, MoodCheckin::where("user_id", $other->id)->where("mood", 1)->count());
    }

    public function test_today_endpoint_gates_the_modal(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson("/api/v2/user/mood-checkins/today")
            ->assertStatus(200)
            ->assertJsonPath("data.checked_in", false)
            ->assertJsonPath("data.mood", null);

        $this->postJson("/api/v2/user/mood-checkins", ["mood" => 3])->assertStatus(200);

        $this->getJson("/api/v2/user/mood-checkins/today")
            ->assertJsonPath("data.checked_in", true)
            ->assertJsonPath("data.mood", 3);
    }

    public function test_mood_endpoints_require_authentication(): void
    {
        $this->postJson("/api/v2/user/mood-checkins", ["mood" => 3])->assertStatus(401);
        $this->getJson("/api/v2/user/mood-checkins/today")->assertStatus(401);
    }
}
