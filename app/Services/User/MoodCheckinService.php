<?php

namespace App\Services\User;

use App\Constants\Account\User\MoodConstants;
use App\Models\MoodCheckin;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class MoodCheckinService
{
    /**
     * One check-in per calendar day — a same-day repeat updates the row.
     */
    public function checkIn(User $user, array $data): MoodCheckin
    {
        $validator = Validator::make($data, [
            'mood' => 'required|integer|between:' . MoodConstants::MIN . ',' . MoodConstants::MAX,
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return MoodCheckin::updateOrCreate([
            'user_id' => $user->id,
            'checked_in_on' => now()->toDateString(),
        ], [
            'mood' => $validator->validated()['mood'],
        ]);
    }

    public static function today(User $user): ?MoodCheckin
    {
        return MoodCheckin::where([
            'user_id' => $user->id,
            'checked_in_on' => now()->toDateString(),
        ])->first();
    }
}
