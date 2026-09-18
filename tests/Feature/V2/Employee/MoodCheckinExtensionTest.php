<?php

namespace Tests\Feature\V2\Employee;

use App\Models\MoodCheckin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MoodCheckinExtensionTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    /** The mobile contract (§03) is a bare {mood} — it must keep working. */
    public function test_a_bare_mood_payload_still_works(): void
    {
        $user = $this->actor();

        $this->postJson("/api/v2/user/mood-checkins", ["mood" => 4])
            ->assertStatus(200)
            ->assertJsonPath("data.mood", 4);

        $row = MoodCheckin::where("user_id", $user->id)->first();
        $this->assertSame(4, $row->mood);
        $this->assertNull($row->factors);
        $this->assertNull($row->note);
    }

    public function test_factors_and_note_are_stored(): void
    {
        $user = $this->actor();

        $this->postJson("/api/v2/user/mood-checkins", [
            "mood" => 3,
            "factors" => ["work", "sleep"],
            "note" => "Tired but managed the workload.",
        ])
            ->assertStatus(200)
            ->assertJsonPath("data.checkin.factors", ["work", "sleep"])
            ->assertJsonPath("data.checkin.factor_labels", ["Work", "Sleep"])
            ->assertJsonPath("data.checkin.note", "Tired but managed the workload.");

        $row = MoodCheckin::where("user_id", $user->id)->first();
        $this->assertSame(["work", "sleep"], $row->factors);
    }

    public function test_duplicate_factors_are_collapsed(): void
    {
        $user = $this->actor();

        $this->postJson("/api/v2/user/mood-checkins", [
            "mood" => 3,
            "factors" => ["work", "work", "rest"],
        ])->assertStatus(200);

        $this->assertSame(["work", "rest"], MoodCheckin::where("user_id", $user->id)->first()->factors);
    }

    public function test_unknown_factor_keys_are_rejected(): void
    {
        $user = $this->actor();

        $this->postJson("/api/v2/user/mood-checkins", [
            "mood" => 3,
            "factors" => ["work", "astrology"],
        ])->assertStatus(422);

        $this->assertSame(0, MoodCheckin::where("user_id", $user->id)->count());
    }

    public function test_an_over_long_note_is_rejected(): void
    {
        $user = $this->actor();

        $this->postJson("/api/v2/user/mood-checkins", [
            "mood" => 3,
            "note" => str_repeat("a", 281),
        ])->assertStatus(422);

        $this->assertSame(0, MoodCheckin::where("user_id", $user->id)->count());
    }

    public function test_a_same_day_repeat_updates_rather_than_duplicates(): void
    {
        $user = $this->actor();

        $this->postJson("/api/v2/user/mood-checkins", ["mood" => 2, "factors" => ["work"]])
            ->assertStatus(200);
        $this->postJson("/api/v2/user/mood-checkins", ["mood" => 5, "factors" => ["rest"]])
            ->assertStatus(200);

        $rows = MoodCheckin::where("user_id", $user->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame(5, $rows->first()->mood);
        $this->assertSame(["rest"], $rows->first()->factors);
    }

    /**
     * A mobile check-in later the same day updates the mood without wiping
     * factors the web already recorded — `sometimes` on both keys.
     */
    public function test_a_later_bare_payload_does_not_wipe_the_days_factors(): void
    {
        $user = $this->actor();

        $this->postJson("/api/v2/user/mood-checkins", ["mood" => 2, "factors" => ["work"], "note" => "hm"])
            ->assertStatus(200);
        $this->postJson("/api/v2/user/mood-checkins", ["mood" => 4])->assertStatus(200);

        $row = MoodCheckin::where("user_id", $user->id)->first();
        $this->assertSame(4, $row->mood);
        $this->assertSame(["work"], $row->factors);
        $this->assertSame("hm", $row->note);
    }

    public function test_today_returns_the_factor_vocabulary_the_screen_renders(): void
    {
        $this->actor();

        $this->getJson("/api/v2/user/mood-checkins/today")
            ->assertStatus(200)
            ->assertJsonPath("data.checked_in", false)
            ->assertJsonCount(8, "data.factors")
            ->assertJsonPath("data.factors.0.key", "work")
            ->assertJsonPath("data.factors.0.label", "Work");
    }

    public function test_history_is_the_callers_own_newest_first(): void
    {
        $user = $this->actor();
        $other = User::factory()->create();

        foreach ([["2026-07-05", 2], ["2026-07-07", 4], ["2026-07-06", 3]] as [$date, $mood]) {
            MoodCheckin::create([
                "user_id" => $user->id,
                "mood" => $mood,
                "checked_in_on" => $date,
                "factors" => ["work"],
            ]);
        }

        MoodCheckin::create([
            "user_id" => $other->id,
            "mood" => 1,
            "checked_in_on" => "2026-07-07",
        ]);

        $rows = $this->getJson("/api/v2/user/mood-checkins")
            ->assertStatus(200)
            ->json("data.data");

        $this->assertCount(3, $rows);
        $this->assertSame(["2026-07-07", "2026-07-06", "2026-07-05"], array_column($rows, "date"));
        $this->assertSame(["Work"], $rows[0]["factor_labels"]);
    }

    public function test_endpoints_require_authentication(): void
    {
        foreach (["/api/v2/user/mood-checkins", "/api/v2/user/mood-checkins/summary"] as $uri) {
            $this->getJson($uri)->assertStatus(401);
        }

        $this->postJson("/api/v2/user/mood-checkins", ["mood" => 3])->assertStatus(401);
    }
}
