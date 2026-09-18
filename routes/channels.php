<?php

use App\Models\ConversationMember;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| §16/§3b hardening: conversation channels require membership and the
| refresh channel requires identity — previously any authenticated user
| could subscribe to any private channel (verified v1 bug #7). Legitimate
| clients are members of the channels they subscribe to and are unaffected.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('presence-user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
| Registered WITHOUT the "private-"/"presence-" prefix on purpose — Laravel
| strips that prefix from the incoming channel name before matching it
| against these patterns (see Broadcaster::normalizeChannelName /
| UsePusherChannelConventions), so a pattern that includes the prefix can
| never match and auth silently 403s. The client still subscribes to the
| prefixed name ("private-conversation.{id}"); only the registration here
| stays bare.
*/
Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    return ConversationMember::where([
        'conversation_id' => $conversationId,
        'user_id' => $user->id,
    ])->exists();
});

Broadcast::channel('refresh-notification.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
