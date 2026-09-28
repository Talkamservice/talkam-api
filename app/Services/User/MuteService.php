<?php

namespace App\Services\User;

use App\Exceptions\General\InvalidRequestException;
use App\Models\User;
use App\Models\UserMute;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class MuteService
{
    /**
     * Mute/unmute a user. Feeds-only: hides their posts and comments from
     * the caller's v2 feeds — DMs and visibility remain Block's job.
     */
    public function toggle(User $user, array $data): bool
    {
        $validator = Validator::make($data, [
            'user_id' => 'required|numeric|exists:users,id',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $muted_user_id = (int) $validator->validated()['user_id'];

        if ($muted_user_id === (int) $user->id) {
            throw new InvalidRequestException("You cannot mute yourself.");
        }

        $existing = UserMute::where([
            'user_id' => $user->id,
            'muted_user_id' => $muted_user_id,
        ])->first();

        if ($existing) {
            $existing->delete();
            return false;
        }

        UserMute::firstOrCreate([
            'user_id' => $user->id,
            'muted_user_id' => $muted_user_id,
        ]);

        return true;
    }

    public static function muted(User $user)
    {
        return User::whereIn('id', UserMute::where('user_id', $user->id)->pluck('muted_user_id'));
    }

    public static function mutedIds(?User $user): array
    {
        if (empty($user)) {
            return [];
        }

        return UserMute::where('user_id', $user->id)->pluck('muted_user_id')->all();
    }
}
