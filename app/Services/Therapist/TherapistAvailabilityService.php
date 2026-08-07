<?php

namespace App\Services\Therapist;

use App\Models\TherapistAvailability;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The live weekly availability grid — the Availability screen's read/write.
 *
 * This grid is authoritative for FUTURE bookings only. The §07 slot engine
 * computes concrete bookable slots from it; editing the grid never rewrites an
 * already-booked therapy_sessions row (those reference a concrete starts_at).
 */
class TherapistAvailabilityService
{
    const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    /** Deck short keys ↔ the full day names §06 stores in day_of_week. */
    const DAY_TO_NAME = [
        'mon' => 'monday', 'tue' => 'tuesday', 'wed' => 'wednesday', 'thu' => 'thursday',
        'fri' => 'friday', 'sat' => 'saturday', 'sun' => 'sunday',
    ];

    public static function grid(User $user): array
    {
        $rows = TherapistAvailability::where('user_id', $user->id)
            ->orderBy('start_time')
            ->get();

        $name_to_day = array_flip(self::DAY_TO_NAME);
        $grid = array_fill_keys(self::DAYS, []);
        $active = array_fill_keys(self::DAYS, false);

        foreach ($rows as $row) {
            $day = $name_to_day[$row->day_of_week] ?? null;
            if (empty($day)) {
                continue;
            }

            if ($row->active) {
                $active[$day] = true;
            }

            $grid[$day][] = [
                'id' => $row->id,
                'start' => substr((string) $row->start_time, 0, 5),
                'end' => substr((string) $row->end_time, 0, 5),
                'active' => (bool) $row->active,
            ];
        }

        return [
            'days' => $active,
            'slots' => $grid,
        ];
    }

    /**
     * Replace the whole recurring schedule with the submitted grid. A full
     * replace keeps the client simple (send the grid you want); booked sessions
     * are untouched because they never read this table.
     */
    public function replace(User $user, array $data): array
    {
        $validator = Validator::make($data, [
            'days' => 'required|array',
            // present, not required: an empty array turns that day off.
            'days.*' => 'present|array',
            'days.*.*.start' => 'required|date_format:H:i',
            'days.*.*.end' => 'required|date_format:H:i|after:days.*.*.start',
        ]);

        // Only known day keys are accepted.
        $validator->after(function ($validator) use ($data) {
            foreach (array_keys($data['days'] ?? []) as $day) {
                if (!in_array($day, self::DAYS, true)) {
                    $validator->errors()->add('days', "Unknown day: {$day}");
                }
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        DB::beginTransaction();
        try {
            TherapistAvailability::where('user_id', $user->id)->delete();

            foreach ($data['days'] as $day => $slots) {
                foreach ($slots as $slot) {
                    TherapistAvailability::create([
                        'user_id' => $user->id,
                        'day_of_week' => self::DAY_TO_NAME[$day],
                        'start_time' => $slot['start'],
                        'end_time' => $slot['end'],
                        'active' => $slot['active'] ?? true,
                    ]);
                }
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }

        return self::grid($user->refresh());
    }
}
