<?php

namespace App\Services\Therapist;

use App\Models\Therapist;
use App\Models\TherapistAvailability;
use App\Models\TherapySession;
use Carbon\Carbon;

class TherapistSlotService
{
    /**
     * Bookable slots for a date = availability window ÷ (duration + buffer)
     * minus actively held/confirmed bookings.
     */
    public static function slotsFor(Therapist $therapist, string $date): array
    {
        $day = Carbon::parse($date);
        $availability = TherapistAvailability::where([
            'user_id' => $therapist->user_id,
            'day_of_week' => strtolower($day->englishDayOfWeek),
            'active' => true,
        ])->first();

        if (empty($availability) || empty($availability->start_time) || empty($availability->end_time)) {
            return [];
        }

        $duration = (int) ($therapist->session_duration ?: 50);
        $step = $duration + (int) ($therapist->buffer_minutes ?: 0);

        $window_start = $day->copy()->setTimeFromTimeString($availability->start_time);
        $window_end = $day->copy()->setTimeFromTimeString($availability->end_time);

        $taken = TherapySession::where('therapist_id', $therapist->id)
            ->active()
            ->whereBetween('starts_at', [$window_start, $window_end])
            ->pluck('starts_at')
            ->map(fn ($t) => Carbon::parse($t)->format('Y-m-d H:i'))
            ->all();

        $slots = [];
        for ($cursor = $window_start->copy(); $cursor->copy()->addMinutes($duration)->lte($window_end); $cursor->addMinutes($step)) {
            if (in_array($cursor->format('Y-m-d H:i'), $taken)) {
                continue;
            }
            if ($cursor->isPast()) {
                continue;
            }
            $slots[] = [
                'starts_at' => $cursor->toDateTimeString(),
                'ends_at' => $cursor->copy()->addMinutes($duration)->toDateTimeString(),
            ];
        }

        return $slots;
    }

    /**
     * The soonest bookable slot over the next $days days — powers the
     * directory card's "next availability".
     */
    public static function nextSlot(Therapist $therapist, int $days = 14): ?array
    {
        for ($i = 0; $i < $days; $i++) {
            $slots = self::slotsFor($therapist, now()->addDays($i)->toDateString());
            if (!empty($slots)) {
                return $slots[0];
            }
        }

        return null;
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
