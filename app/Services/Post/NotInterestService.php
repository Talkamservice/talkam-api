<?php

namespace App\Services\Post;

use App\Models\PostNotInterest;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class NotInterestService
{
    /**
     * "Not interested in this post" toggle — hides the post from the
     * caller's v2 feeds and doubles as a negative For You signal.
     */
    public function toggle(User $user, array $data): bool
    {
        $validator = Validator::make($data, [
            'post_id' => 'required|numeric|exists:posts,id',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $post_id = (int) $validator->validated()['post_id'];

        $existing = PostNotInterest::where([
            'user_id' => $user->id,
            'post_id' => $post_id,
        ])->first();

        if ($existing) {
            $existing->delete();
            return false;
        }

        PostNotInterest::firstOrCreate([
            'user_id' => $user->id,
            'post_id' => $post_id,
        ]);

        return true;
    }

    public static function notInterestedIds(?User $user): array
    {
        if (empty($user)) {
            return [];
        }

        return PostNotInterest::where('user_id', $user->id)->pluck('post_id')->all();
    }
}
