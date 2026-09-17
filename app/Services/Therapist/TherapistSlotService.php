<?php

namespace App\Services\Therapist;

use App\Models\Therapist;
use App\Models\TherapistAvailability;
use App\Models\TherapySession;
use Carbon\Carbon;

class TherapistSlotService
{
    /**
     * All active availability windows, grouped by day_of_week — one query
     * regardless of how many days get scanned, instead of re-querying the
     * same small, rarely-changing grid once per day inside a nextSlot()/
     * upcomingSlots() loop.
     */
    private static function windowsByDay(Therapist $therapist): array
    {
        return TherapistAvailability::where('user_id', $therapist->user_id)
            ->where('active', true)
            ->get()
            ->filter(fn ($w) => !empty($w->start_time) && !empty($w->end_time))
            ->groupBy('day_of_week')
            ->all();
    }

    /**
     * Taken (active) starts_at, grouped by date string, across a whole scan
     * range in one query — same "batch once, not once per day" reasoning.
     */
    private static function takenByDate(Therapist $therapist, Carbon $from, Carbon $to): array
    {
        return TherapySession::where('therapist_id', $therapist->id)
            ->active()
            ->whereBetween('starts_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->pluck('starts_at')
            ->map(fn ($t) => Carbon::parse($t))
            ->groupBy(fn ($t) => $t->format('Y-m-d'))
            ->map(fn ($group) => $group->map(fn ($t) => $t->format('Y-m-d H:i'))->all())
            ->all();
    }

    /**
     * Bookable slots for a date = availability window ÷ (duration + buffer)
     * minus actively held/confirmed bookings.
     *
     * $windows_by_day/$taken_by_date let a caller scanning many days (nextSlot,
     * upcomingSlots) pass in data it already batch-fetched once instead of
     * this method re-querying per day; omitted, it fetches for just this one
     * date as before — existing single-date callers (isBookable) are unaffected.
     */
    public static function slotsFor(Therapist $therapist, string $date, ?array $windows_by_day = null, ?array $taken_by_date = null): array
    {
        $day = Carbon::parse($date);
        $day_of_week = strtolower($day->englishDayOfWeek);

        // The availability grid stores one row PER bookable block, so a weekday
        // can have several windows (08:00–08:50, 09:00–09:50, …). Read them all,
        // not just the first — otherwise only the earliest window is ever
        // offered, and once it is in the past the day looks fully booked.
        $windows = $windows_by_day !== null
            ? collect($windows_by_day[$day_of_week] ?? [])
            : TherapistAvailability::where([
                'user_id' => $therapist->user_id,
                'day_of_week' => $day_of_week,
                'active' => true,
            ])->get()->filter(fn ($w) => !empty($w->start_time) && !empty($w->end_time));

        if ($windows->isEmpty()) {
            return [];
        }

        $duration = (int) ($therapist->session_duration ?: 50);
        $step = $duration + (int) ($therapist->buffer_minutes ?: 0);

        $taken = $taken_by_date !== null
            ? ($taken_by_date[$day->format('Y-m-d')] ?? [])
            : TherapySession::where('therapist_id', $therapist->id)
                ->active()
                ->whereBetween('starts_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
                ->pluck('starts_at')
                ->map(fn ($t) => Carbon::parse($t)->format('Y-m-d H:i'))
                ->all();

        $slots = [];
        foreach ($windows as $window) {
            $window_start = $day->copy()->setTimeFromTimeString($window->start_time);
            $window_end = $day->copy()->setTimeFromTimeString($window->end_time);

            for ($cursor = $window_start->copy(); $cursor->copy()->addMinutes($duration)->lte($window_end); $cursor->addMinutes($step)) {
                $key = $cursor->format('Y-m-d H:i');
                if (in_array($key, $taken) || $cursor->isPast()) {
                    continue;
                }
                // Overlapping windows (e.g. 16:00–16:50 and 16:30–17:20) can
                // land on the same start; keep one entry per start time.
                $slots[$key] = [
                    'starts_at' => $cursor->toDateTimeString(),
                    'ends_at' => $cursor->copy()->addMinutes($duration)->toDateTimeString(),
                ];
            }
        }

        ksort($slots);

        return array_values($slots);
    }

    /**
     * The soonest bookable slot over the next $days days — powers the
     * directory card's "next availability".
     */
    public static function nextSlot(Therapist $therapist, int $days = 14): ?array
    {
        $windows_by_day = self::windowsByDay($therapist);
        if (empty($windows_by_day)) {
            return null;
        }

        $taken_by_date = self::takenByDate($therapist, now(), now()->addDays($days));

        for ($i = 0; $i < $days; $i++) {
            $slots = self::slotsFor($therapist, now()->addDays($i)->toDateString(), $windows_by_day, $taken_by_date);
            if (!empty($slots)) {
                return $slots[0];
            }
        }

        return null;
    }

    /**
     * Up to $limit bookable slots, soonest first, scanning forward across
     * $days days — the no-date-picker booking/reschedule flow just wants
     * "what can I grab soon", not a specific day. Stops as soon as $limit is
     * reached rather than scanning every remaining day once it has enough.
     */
    public static function upcomingSlots(Therapist $therapist, int $days = 14, int $limit = 6): array
    {
        $windows_by_day = self::windowsByDay($therapist);
        if (empty($windows_by_day)) {
            return [];
        }

        $taken_by_date = self::takenByDate($therapist, now(), now()->addDays($days));
        $slots = [];

        for ($i = 0; $i < $days && count($slots) < $limit; $i++) {
            $slots = array_merge($slots, self::slotsFor($therapist, now()->addDays($i)->toDateString(), $windows_by_day, $taken_by_date));
        }

        return array_slice($slots, 0, $limit);
    }

    public static function isBookable(Therapist $therapist, string $starts_at): bool
    {
        $target = Carbon::parse($starts_at);
        $slots = self::slotsFor($therapist, $target->toDateString());

        return collect($slots)->contains(
            fn ($slot) => Carbon::parse($slot['starts_at'])->equalTo($target)
        );
    }
}
