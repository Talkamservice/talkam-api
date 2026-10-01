<?php

namespace App\Services\Messaging\V2;

use App\Constants\Messaging\MessagingV2Constants;
use App\Models\ConversationMember;
use App\Models\User;
use App\Services\User\PrivacySettingService;
use Illuminate\Support\Facades\Cache;

class PresenceService
{
    // Clients are expected to re-post presence at least this often while active.
    const TTL_SECONDS = 300;

    public static function canView(User $viewer, int $targetId): bool
    {
        if ((int) $viewer->id === $targetId) {
            return true;
        }

        $target = User::find($targetId);

        if (! $target || ! PrivacySettingService::forUser($target)['activity_status']) {
            return false;
        }

        return ConversationMember::where('user_id', $viewer->id)
            ->whereIn('conversation_id', ConversationMember::where('user_id', $targetId)->select('conversation_id'))
            ->exists();
    }

    public static function set(int $userId, string $status): void
    {
        if ($status === MessagingV2Constants::PRESENCE_OFFLINE) {
            Cache::forget(self::key($userId));
            return;
        }

        Cache::put(self::key($userId), $status, self::TTL_SECONDS);
    }

    public static function get(int $userId): string
    {
        return Cache::get(self::key($userId), MessagingV2Constants::PRESENCE_OFFLINE);
    }

    private static function key(int $userId): string
    {
        return "presence.user.{$userId}";
    }
}
