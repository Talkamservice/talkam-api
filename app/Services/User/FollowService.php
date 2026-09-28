<?php

namespace App\Services\User;

use App\Exceptions\General\InvalidRequestException;
use App\Models\Post;
use App\Models\User;
use App\Models\UserFollow;
use App\Notifications\Post\NewFollowedUserPostNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FollowService
{
    /**
     * Subscribe/unsubscribe to an author. Returns whether the caller now
     * follows the target.
     */
    public function toggle(User $user, array $data): bool
    {
        $validator = Validator::make($data, [
            'user_id' => 'required|numeric|exists:users,id',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $followed_id = (int) $validator->validated()['user_id'];

        if ($followed_id === (int) $user->id) {
            throw new InvalidRequestException("You cannot subscribe to yourself.");
        }

        $existing = UserFollow::where([
            'follower_id' => $user->id,
            'followed_id' => $followed_id,
        ])->first();

        if ($existing) {
            $existing->delete();
            return false;
        }

        UserFollow::firstOrCreate([
            'follower_id' => $user->id,
            'followed_id' => $followed_id,
        ]);

        return true;
    }

    public static function following(User $user)
    {
        return User::whereIn('id', UserFollow::where('follower_id', $user->id)->pluck('followed_id'));
    }

    public static function followers(User $user)
    {
        return User::whereIn('id', UserFollow::where('followed_id', $user->id)->pluck('follower_id'));
    }

    /**
     * Decided semantics: followers are notified of a followed author's new
     * non-anonymous published posts only.
     */
    public static function notifyFollowersOfNewPost(Post $post): void
    {
        if ($post->is_anonymous == 1) {
            return;
        }

        $followers = User::whereIn(
            'id',
            UserFollow::where('followed_id', $post->user_id)->pluck('follower_id')
        )->get();

        if ($followers->isNotEmpty()) {
            Notification::send($followers, new NewFollowedUserPostNotification($post));
        }
    }
}
