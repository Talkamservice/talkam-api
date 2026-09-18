<?php

namespace Tests\Feature\V2\Employee;

use App\Models\MoodCheckin;
use App\Models\User;
use App\Services\User\MoodCheckinService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MoodSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    private function log(User $user, string $date, int $mood, array $factors = []): void
    {
        MoodCheckin::create([
            "user_id" => $user->id,
            "mood" => $mood,
            "checked_in_on" => $date,
            "factors" => $factors ?: null,
        ]);
    }

    private function daysAgo(int $n): string
    {
        return Carbon::today()->subDays($n)->toDateString();
    }

    /* ── Streak ─────────────────────────────────────────────────────────── */

    public function test_streak_counts_consecutive_days_ending_today(): void
    {
        $user = $this->actor();

        foreach ([0, 1, 2] as $n) {
            $this->log($user, $this->daysAgo($n), 4);
        }

        $this->assertSame(3, MoodCheckinService::streak($user));
    }

    /** A streak should not read as broken before today's check-in is made. */
    public function test_streak_still_counts_when_the_last_entry_is_yesterday(): void
    {
        $user = $this->actor();

        foreach ([1, 2, 3] as $n) {
            $this->log($user, $this->daysAgo($n), 4);
        }

        $this->assertSame(3, MoodCheckinService::streak($user));
    }

    public function test_streak_breaks_on_a_gap(): void
    {
        $user = $this->actor();

        $this->log($user, $this->daysAgo(0), 4);
        $this->log($user, $this->daysAgo(1), 4);
        // gap at day 2
        $this->log($user, $this->daysAgo(3), 4);
        $this->log($user, $this->daysAgo(4), 4);

        $this->assertSame(2, MoodCheckinService::streak($user));
    }

    public function test_streak_is_zero_when_the_last_entry_is_stale_or_absent(): void
    {
        $user = $this->actor();
        $this->assertSame(0, MoodCheckinService::streak($user));

        $this->log($user, $this->daysAgo(5), 4);
        $this->assertSame(0, MoodCheckinService::streak($user));
    }

    /* ── Series & counts ────────────────────────────────────────────────── */

    public function test_series_has_one_entry_per_day_with_null_for_missed_days(): void
    {
        $user = $this->actor();

        $this->log($user, $this->daysAgo(0), 5);
        $this->log($user, $this->daysAgo(3), 2);

        $data = $this->getJson("/api/v2/user/mood-checkins/summary?days=14")
            ->assertStatus(200)
            ->json("data");

        $this->assertCount(14, $data["series"]);
        $this->assertSame(2, $data["days_logged"]);

        // Oldest first, newest last.
        $this->assertSame($this->daysAgo(13), $data["series"][0]["date"]);
        $this->assertSame($this->daysAgo(0), $data["series"][13]["date"]);
        $this->assertSame(5, $data["series"][13]["mood"]);
        $this->assertSame(2, $data["series"][10]["mood"]);
        $this->assertNull($data["series"][12]["mood"]);

        $this->assertEquals(3.5, $data["average_mood"]);
    }

    public function test_entries_outside_the_window_are_excluded(): void
    {
        $user = $this->actor();

        $this->log($user, $this->daysAgo(0), 5);
        $this->log($user, $this->daysAgo(20), 1);

        $data = $this->getJson("/api/v2/user/mood-checkins/summary?days=14")->json("data");

        $this->assertSame(1, $data["days_logged"]);
        $this->assertEquals(5.0, $data["average_mood"]);
    }

    public function test_the_window_length_is_configurable_and_clamped(): void
    {
        $this->actor();

        $this->assertCount(
            7,
            $this->getJson("/api/v2/user/mood-checkins/summary?days=7")->json("data.series")
        );

        // Absurd values clamp rather than blow up.
        $this->assertCount(
            366,
            $this->getJson("/api/v2/user/mood-checkins/summary?days=99999")->json("data.series")
        );
        $this->assertCount(
            1,
            $this->getJson("/api/v2/user/mood-checkins/summary?days=0")->json("data.series")
        );
    }

    public function test_an_empty_history_returns_zeroes_not_nulls_where_it_matters(): void
    {
        $this->actor();

        $data = $this->getJson("/api/v2/user/mood-checkins/summary")->json("data");

        $this->assertSame(0, $data["streak"]);
        $this->assertSame(0, $data["days_logged"]);
        $this->assertNull($data["average_mood"]);
        $this->assertNull($data["month_delta_percent"]);
        $this->assertSame([], $data["top_factors"]);
    }

    /* ── Month delta ────────────────────────────────────────────────────── */

    public function test_month_delta_compares_this_month_against_last(): void
    {
        $user = $this->actor();

        $this_month = Carbon::today()->startOfMonth();
        $last_month = $this_month->copy()->subMonth();

        // Last month mean 2, this month mean 3 → +50%.
        $this->log($user, $last_month->copy()->addDays(1)->toDateString(), 2);
        $this->log($user, $last_month->copy()->addDays(2)->toDateString(), 2);
        $this->log($user, $this_month->copy()->toDateString(), 3);

        $this->assertEquals(50.0, MoodCheckinService::monthDelta($user));
    }

    /** "No data" and "no change" are different answers. */
    public function test_month_delta_is_null_without_a_prior_month(): void
    {
        $user = $this->actor();

        $this->log($user, Carbon::today()->startOfMonth()->toDateString(), 4);

        $this->assertNull(MoodCheckinService::monthDelta($user));
    }

    /* ── Top factors ────────────────────────────────────────────────────── */

    public function test_top_factors_rank_by_frequency_as_a_percentage_of_tagged_days(): void
    {
        $user = $this->actor();

        // 4 tagged days: work on all 4, sleep on 2, rest on 1.
        $this->log($user, $this->daysAgo(0), 3, ["work", "sleep"]);
        $this->log($user, $this->daysAgo(1), 3, ["work", "sleep"]);
        $this->log($user, $this->daysAgo(2), 3, ["work", "rest"]);
        $this->log($user, $this->daysAgo(3), 3, ["work"]);
        // An untagged day must not dilute the percentages.
        $this->log($user, $this->daysAgo(4), 3);

        $factors = $this->getJson("/api/v2/user/mood-checkins/summary")->json("data.top_factors");

        $this->assertSame("work", $factors[0]["key"]);
        $this->assertSame("Work", $factors[0]["label"]);
        $this->assertSame(100, $factors[0]["percent"]);
        $this->assertSame("sleep", $factors[1]["key"]);
        $this->assertSame(50, $factors[1]["percent"]);
        $this->assertSame(25, $factors[2]["percent"]);
    }

    public function test_top_factors_are_capped_at_three(): void
    {
        $user = $this->actor();

        $this->log($user, $this->daysAgo(0), 3, ["work", "sleep", "rest", "family", "health"]);

        $this->assertCount(
            3,
            $this->getJson("/api/v2/user/mood-checkins/summary")->json("data.top_factors")
        );
    }

    /* ── Scoping ────────────────────────────────────────────────────────── */

    public function test_the_summary_never_includes_another_users_data(): void
    {
        $user = $this->actor();
        $other = User::factory()->create();

        $this->log($user, $this->daysAgo(0), 5);
        foreach ([0, 1, 2, 3] as $n) {
            $this->log($other, $this->daysAgo($n), 1);
        }

        $data = $this->getJson("/api/v2/user/mood-checkins/summary")->json("data");

        $this->assertSame(1, $data["days_logged"]);
        $this->assertEquals(5.0, $data["average_mood"]);
        $this->assertSame(1, $data["streak"]);
    }
}
