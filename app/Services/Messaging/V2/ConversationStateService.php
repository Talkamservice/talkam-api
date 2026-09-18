<?php

namespace App\Services\Messaging\V2;

use App\Constants\General\StatusConstants;
use App\Events\Messaging\ConversationSeen;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\Message;
use App\Models\MessageHide;
use App\Models\User;

class ConversationStateService
{
    /**
     * The caller's member row — membership is the auth gate everywhere.
     */
    public static function memberRow(User $user, $conversation_id): ConversationMember
    {
        $member = ConversationMember::where([
            'conversation_id' => $conversation_id,
            'user_id' => $user->id,
        ])->first();

        if (empty($member)) {
            throw new ModelNotFoundException("Conversation not found");
        }

        return $member;
    }

    public static function isMember(User $user, $conversation_id): bool
    {
        return ConversationMember::where([
            'conversation_id' => $conversation_id,
            'user_id' => $user->id,
        ])->exists();
    }

    public function mute(User $user, $conversation_id, ?string $muted_until = null): ConversationMember
    {
        $member = self::memberRow($user, $conversation_id);
        $member->update([
            'is_muted' => true,
            'muted_at' => now(),
            'muted_until' => $muted_until,
        ]);

        return $member->refresh();
    }

    public function unmute(User $user, $conversation_id): ConversationMember
    {
        $member = self::memberRow($user, $conversation_id);
        $member->update(['is_muted' => false, 'muted_at' => null, 'muted_until' => null]);

        return $member->refresh();
    }

    public function setArchived(User $user, $conversation_id, bool $archived): ConversationMember
    {
        $member = self::memberRow($user, $conversation_id);
        $member->update(['archived_at' => $archived ? now() : null]);

        return $member->refresh();
    }

    public function setStarred(User $user, $conversation_id, bool $starred): ConversationMember
    {
        $member = self::memberRow($user, $conversation_id);
        $member->update(['starred_at' => $starred ? now() : null]);

        return $member->refresh();
    }

    public function seen(User $user, $conversation_id): ConversationMember
    {
        $member = self::memberRow($user, $conversation_id);
        $latest = Message::where('conversation_id', $conversation_id)->latest('id')->first();

        $member->update([
            'last_seen_at' => now(),
            'last_seen_message_id' => $latest?->id,
        ]);

        event(new ConversationSeen((int) $conversation_id, [
            'user_id' => $user->id,
            'last_seen_message_id' => $latest?->id,
        ]));

        return $member->refresh();
    }

    /**
     * Paginated conversations with the genuinely-latest message (v1 bug #3
     * fixed) and unread counts; per-member archived/starred filters.
     */
    public static function list(User $user, array $filters = [])
    {
        $member_rows = ConversationMember::where('user_id', $user->id);

        if (!empty($filters['archived'] ?? null)) {
            $member_rows = $member_rows->whereNotNull('archived_at');
        } else {
            $member_rows = $member_rows->whereNull('archived_at');
        }

        if (!empty($filters['starred'] ?? null)) {
            $member_rows = $member_rows->whereNotNull('starred_at');
        }

        return Conversation::whereIn('id', $member_rows->pluck('conversation_id'))->latest('updated_at');
    }

    public static function serialize(Conversation $conversation, User $viewer): array
    {
        $last_message = Message::where('conversation_id', $conversation->id)->latest('id')->first();
        $hidden_ids = MessageHide::where('user_id', $viewer->id)->pluck('message_id');

        $unread = Message::where('conversation_id', $conversation->id)
            ->where('receiver_id', $viewer->id)
            ->whereNull('read_at')
            ->whereNotIn('id', $hidden_ids)
            ->count();

        $other = $conversation->members()->where('user_id', '!=', $viewer->id)->first()?->user;
        $member = ConversationMember::where([
            'conversation_id' => $conversation->id,
            'user_id' => $viewer->id,
        ])->first();

        return [
            'id' => $conversation->id,
            'status' => $conversation->status,
            'is_anonymous' => (bool) $conversation->is_anonymous,
            'other_member' => empty($other) ? null : [
                'id' => $other->id,
                'name' => $other->full_name,
                'username' => $other->username,
                'avatar' => $other->avatar,
            ],
            'last_message' => empty($last_message) ? null : [
                'id' => $last_message->id,
                'message' => $last_message->message,
                'sender_id' => $last_message->sender_id,
                'created_at' => $last_message->created_at->toDateTimeString(),
            ],
            'unread_count' => $unread,
            'is_muted' => (bool) ($member?->is_muted ?? false),
            'archived_at' => $member?->archived_at?->toDateTimeString(),
            'starred_at' => $member?->starred_at?->toDateTimeString(),
        ];
    }

    /**
     * Conversations awaiting the caller's response (v1's route is dead).
     */
    public static function pendingRequests(User $user)
    {
        return Conversation::where('status', StatusConstants::AWAITING_RESPONSE)
            ->where('user_id', '!=', $user->id)
            ->whereHas('members', fn ($q) => $q->where('user_id', $user->id))
            ->latest();
    }
}
