<?php

namespace App\Services\User;

use App\Constants\Account\User\MoodConstants;
use App\Models\MoodCheckin;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MoodCheckinService
{
    /**
     * One check-in per calendar day — a same-day repeat updates the row.
     *
     * `factors` and `note` are the web §02 additions. Both are optional, so
     * the mobile payload (§03: just {mood}) is unchanged — and because they
     * are only written when present, a later mobile check-in updates the mood
     * without wiping factors an earlier web check-in recorded that day.
     */
    public function checkIn(User $user, array $data): MoodCheckin
    {
        $factor_keys = array_column(config('v2.checkins.factors'), 'key');

        $validator = Validator::make($data, [
            'mood' => 'required|integer|between:' . MoodConstants::MIN . ',' . MoodConstants::MAX,
            'factors' => 'sometimes|array|max:' . count($factor_keys),
            'factors.*' => ['string', Rule::in($factor_keys)],
            'note' => 'sometimes|nullable|string|max:' . config('v2.checkins.note_max_length'),
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        $payload = ['mood' => $validated['mood']];

        if (array_key_exists('factors', $validated)) {
            $payload['factors'] = array_values(array_unique($validated['factors']));
        }

        if (array_key_exists('note', $validated)) {
            $payload['note'] = $validated['note'];
        }

        return MoodCheckin::updateOrCreate([
            'user_id' => $user->id,
            'checked_in_on' => now()->toDateString(),
        ], $payload);
    }

    public static function today(User $user): ?MoodCheckin
    {
        return MoodCheckin::where([
            'user_id' => $user->id,
            'checked_in_on' => now()->toDateString(),
        ])->first();
    }

    /** Newest-first history for the "Recent check-ins" list. */
    public static function history(User $user)
    {
        return MoodCheckin::where('user_id', $user->id)
            ->orderByDesc('checked_in_on');
    }

    /**
     * Everything the two dashboard charts and both stat strips need, in one
     * round-trip: streak, average, days logged, month delta, the day-by-day
     * series and the top factors.
     */
    public static function summary(User $user, ?int $days = null): array
    {
        $days = $days ?: (int) config('v2.checkins.trend_days');
        $today = Carbon::today();
        $window_start = $today->copy()->subDays($days - 1);

        $window = MoodCheckin::where('user_id', $user->id)
            ->where('checked_in_on', '>=', $window_start->toDateString())
            ->orderBy('checked_in_on')
            ->get()
            ->keyBy(fn ($row) => (string) $row->checked_in_on);

        // One entry per day, null where nothing was logged — the chart should
        // never have to infer which day a bar belongs to.
        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $window_start->copy()->addDays($i)->toDateString();

            $series[] = [
                'date' => $date,
                'mood' => ($window[$date] ?? null)?->mood,
            ];
        }

        return [
            'window_days' => $days,
            'streak' => self::streak($user),
            'days_logged' => $window->count(),
            'average_mood' => $window->count() ? round($window->avg('mood'), 2) : null,
            'month_delta_percent' => self::monthDelta($user),
            'series' => $series,
            'top_factors' => self::topFactors($window->all()),
        ];
    }

    /**
     * Consecutive days ending today — or yesterday, so a streak does not read
     * as broken before today's check-in has been made.
     */
    public static function streak(User $user): int
    {
        $dates = MoodCheckin::where('user_id', $user->id)
            ->orderByDesc('checked_in_on')
            ->pluck('checked_in_on')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->unique()
            ->values();

        if ($dates->isEmpty()) {
            return 0;
        }

        $today = Carbon::today();
        $yesterday = $today->copy()->subDay();

        if ($dates->first() === $today->toDateString()) {
            $cursor = $today->copy();
        } elseif ($dates->first() === $yesterday->toDateString()) {
            $cursor = $yesterday->copy();
        } else {
            return 0;
        }

        $streak = 0;
        foreach ($dates as $date) {
            if ($date !== $cursor->toDateString()) {
                break;
            }

            $streak++;
            $cursor->subDay();
        }

        return $streak;
    }

    /**
     * This month's mean mood against last month's, as a percentage change.
     * Null (not zero) when either month has nothing to compare — "no data"
     * and "no change" are different answers and the screen renders them
     * differently.
     */
    public static function monthDelta(User $user): ?float
    {
        $start_this = Carbon::today()->startOfMonth();
        $start_last = $start_this->copy()->subMonth();

        $mean = fn ($from, $to) => MoodCheckin::where('user_id', $user->id)
            ->where('checked_in_on', '>=', $from->toDateString())
            ->where('checked_in_on', '<', $to->toDateString())
            ->avg('mood');

        $last = $mean($start_last, $start_this);
        $current = $mean($start_this, Carbon::today()->addDay());

        if (empty($last) || empty($current)) {
            return null;
        }

        return round((($current - $last) / $last) * 100, 1);
    }

    /**
     * Factor frequency across the window, as a percentage of the days that
     * carried any factor — the deck's "Work 64% / Sleep 48%" bars.
     */
    public static function topFactors(array $rows, int $limit = 3): array
    {
        $labels = collect(config('v2.checkins.factors'))->pluck('label', 'key');

        $counts = [];
        $days_with_factors = 0;

        foreach ($rows as $row) {
            $factors = $row->factors ?? [];

            if (empty($factors)) {
                continue;
            }

            $days_with_factors++;

            foreach ($factors as $key) {
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        if ($days_with_factors === 0) {
            return [];
        }

        arsort($counts);

        return collect($counts)
            ->take($limit)
            ->map(fn ($count, $key) => [
                'key' => $key,
                'label' => $labels[$key] ?? $key,
                'percent' => (int) round(($count / $days_with_factors) * 100),
            ])
            ->values()
            ->all();
    }

    /** Serialization shared by the history list and the today endpoint. */
    public static function serialize(?MoodCheckin $checkin): ?array
    {
        if (empty($checkin)) {
            return null;
        }

        $labels = collect(config('v2.checkins.factors'))->pluck('label', 'key');
        $factors = $checkin->factors ?? [];
        $mood_labels = collect(config('v2.checkins.moods'))->pluck('label', 'value');

        return [
            'id' => $checkin->id,
            'date' => (string) $checkin->checked_in_on,
            'mood' => $checkin->mood,
            'mood_label' => $mood_labels[$checkin->mood] ?? null,
            'factors' => array_values($factors),
            'factor_labels' => array_values(array_map(fn ($k) => $labels[$k] ?? $k, $factors)),
            'note' => $checkin->note,
        ];
    }
}
