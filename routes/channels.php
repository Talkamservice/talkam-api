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

Broadcast::channel('private-conversation.{conversationId}', function ($user, $conversationId) {
    return ConversationMember::where([
        'conversation_id' => $conversationId,
        'user_id' => $user->id,
    ])->exists();
});

Broadcast::channel('refresh-notification.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
