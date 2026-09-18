<?php

namespace App\Services\Messaging\V2;

use App\Constants\Messaging\MessagingV2Constants;
use App\Events\Messaging\MessageDeleted;
use App\Events\Messaging\MessageDelivered;
use App\Events\Messaging\MessageEdited;
use App\Events\Messaging\MessagePinned;
use App\Events\Messaging\MessageReactionAdded;
use App\Events\Messaging\MessageReactionRemoved;
use App\Events\Messaging\MessageRead;
use App\Events\Messaging\MessageUnpinned;
use App\Events\ReceiveMessage;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\BlockedUser;
use App\Models\Message;
use App\Models\MessageEdit;
use App\Models\MessageHide;
use App\Models\MessageReaction;
use App\Models\User;
use App\Notifications\Messaging\NewMessageNotification;
use App\Services\Media\FileService;
use App\Services\User\PrivacySettingService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MessageActionService
{
    /**
     * A message the caller can act on, membership-gated.
     */
    public static function messageForMember(User $user, $message_id): Message
    {
        $message = Message::find($message_id);

        if (empty($message) || !ConversationStateService::isMember($user, $message->conversation_id)) {
            throw new ModelNotFoundException("Message not found");
        }

        return $message;
    }

    /**
     * v2 send: membership + block enforcement, config length cap, file or
     * voice attachment, delivered_at stamped, events + (mute-aware)
     * notification.
     */
    public function send(User $sender, array $data): Message
    {
        $validator = Validator::make($data, [
            'conversation_id' => 'required|exists:conversations,id',
            'message' => 'nullable|string|max:' . config('v2.messaging.max_length'),
            'message_type' => 'nullable|string',
            'file' => 'nullable|file|max:' . config('v2.messaging.file_max_kb'),
            'voice_duration' => 'nullable|integer|min:0',
            'replied_to_message_id' => 'nullable|exists:messages,id',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        $member = ConversationStateService::memberRow($sender, $validated['conversation_id']);
        $conversation = $member->conversation;

        $receiver = $conversation->members()
            ->where('user_id', '!=', $sender->id)
            ->first()?->user;

        if (empty($receiver)) {
            throw new InvalidRequestException("No counterpart in this conversation.");
        }

        // Block enforcement (§04 blocked_users) — display-only in v1.
        $blocked = BlockedUser::where([
            'blocker_id' => $receiver->id,
            'blocked_user_id' => $sender->id,
        ])->exists();

        if ($blocked) {
            throw new InvalidRequestException("You cannot message this user.");
        }

        if (empty($validated['message']) && empty($validated['file'])) {
            throw ValidationException::withMessages([
                'message' => ['A message or attachment is required.'],
            ]);
        }

        $file_id = null;
        if (!empty($validated['file'])) {
            $tmp_path = \App\Helpers\MethodsHelper::putFileInPrivateStorage(
                $validated['file'],
                \App\Constants\Media\FileConstants::TMP_PATH
            );
            $file_id = (new FileService)->save(storage_path("app/" . $tmp_path), "message-attachments", null, $sender->id)->id;
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'message' => $validated['message'] ?? null,
            'message_type' => $validated['message_type'] ?? 'text',
            'file_id' => $file_id,
            'voice_duration' => $validated['voice_duration'] ?? null,
            'replied_to_message_id' => $validated['replied_to_message_id'] ?? null,
            'read' => false,
            'delivered_at' => now(),
        ]);

        if (!empty($validated['replied_to_message_id'])) {
            Message::where('id', $validated['replied_to_message_id'])->increment('reply_count');
        }

        broadcast(new ReceiveMessage($message->toArray(), $conversation->id, $receiver->id))->toOthers();
        event(new MessageDelivered($conversation->id, ['message_id' => $message->id]));

        // Notification path is mute-aware (per-member + §04 user mutes) via
        // the notification's own via() guard.
        Notification::send($receiver, new NewMessageNotification($message));

        return $message;
    }

    /**
     * Reader-side marking: only inbound messages, read_at stamped, events
     * suppressed when the reader's §09 read_receipts toggle is off.
     */
    public static function markInboundRead(User $reader, $conversation_id): void
    {
        $unread = Message::where('conversation_id', $conversation_id)
            ->where('receiver_id', $reader->id)
            ->whereNull('read_at')
            ->get();

        if ($unread->isEmpty()) {
            return;
        }

        Message::whereIn('id', $unread->pluck('id'))->update(['read' => true, 'read_at' => now()]);

        if (PrivacySettingService::forUser($reader)['read_receipts']) {
            foreach ($unread as $message) {
                event(new MessageRead((int) $conversation_id, [
                    'message_id' => $message->id,
                    'reader_id' => $reader->id,
                ]));
            }
        }
    }

    public function edit(User $user, array $data): Message
    {
        $validator = Validator::make($data, [
            'message_id' => 'required|exists:messages,id',
            'message' => 'required|string|max:' . config('v2.messaging.max_length'),
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $message = self::messageForMember($user, $data['message_id']);

        if ($message->sender_id != $user->id) {
            throw new InvalidRequestException("You can only edit your own messages.");
        }

        $window = (int) config('v2.messaging.edit_window_minutes');
        if ($window > 0 && $message->created_at->diffInMinutes(now()) > $window) {
            throw ValidationException::withMessages([
                'message_id' => ["Messages can only be edited within $window minutes."],
            ]);
        }

        MessageEdit::create([
            'message_id' => $message->id,
            'original_content' => $message->message,
            'new_content' => $data['message'],
            'edited_by' => $user->id,
        ]);

        $message->update([
            'original_message' => $message->original_message ?? $message->message,
            'message' => $data['message'],
            'edited_at' => now(),
        ]);

        event(new MessageEdited($message->conversation_id, [
            'message_id' => $message->id,
            'message' => $message->message,
        ]));

        return $message->refresh();
    }

    public function delete(User $user, $message_id, string $type): void
    {
        if (!in_array($type, MessagingV2Constants::DELETE_TYPES)) {
            throw ValidationException::withMessages(['type' => ['Invalid delete type.']]);
        }

        $message = self::messageForMember($user, $message_id);

        if ($type == MessagingV2Constants::DELETE_FOR_ME) {
            MessageHide::firstOrCreate([
                'user_id' => $user->id,
                'message_id' => $message->id,
            ]);
            return;
        }

        // for_everyone: sender-only, config window, soft delete + blank.
        if ($message->sender_id != $user->id) {
            throw new InvalidRequestException("Only the sender can delete a message for everyone.");
        }

        $window = (int) config('v2.messaging.delete_window_minutes');
        if ($window > 0 && $message->created_at->diffInMinutes(now()) > $window) {
            throw ValidationException::withMessages([
                'message_id' => ["Messages can only be deleted for everyone within $window minutes."],
            ]);
        }

        $conversation_id = $message->conversation_id;

        $message->update([
            'message' => null,
            'asset_url' => null,
            'file_id' => null,
            'delete_type' => MessagingV2Constants::DELETE_FOR_EVERYONE,
            'deleted_by' => $user->id,
        ]);
        $message->delete();

        event(new MessageDeleted($conversation_id, ['message_id' => $message_id]));
    }

    public function forward(User $user, array $data): Message
    {
        $validator = Validator::make($data, [
            'message_id' => 'required|exists:messages,id',
            'conversation_id' => 'required|exists:conversations,id',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $original = self::messageForMember($user, $data['message_id']);

        $forwarded = $this->send($user, [
            'conversation_id' => $data['conversation_id'],
            'message' => $original->message,
            'message_type' => $original->message_type,
        ]);

        $forwarded->update([
            'is_forwarded' => true,
            'forwarded_from_message_id' => $original->id,
        ]);

        return $forwarded->refresh();
    }

    public function addReaction(User $user, array $data): MessageReaction
    {
        $validator = Validator::make($data, [
            'message_id' => 'required|exists:messages,id',
            'reaction' => 'required|string|max:' . config('v2.messaging.reaction_max_length'),
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $message = self::messageForMember($user, $data['message_id']);

        $existing = MessageReaction::where([
            'message_id' => $message->id,
            'user_id' => $user->id,
            'reaction' => $data['reaction'],
        ])->first();

        if (!empty($existing)) {
            // Friendly duplicate response — not Fundnai's raw 500.
            throw new InvalidRequestException("You have already reacted with this.");
        }

        $reaction = MessageReaction::create([
            'message_id' => $message->id,
            'user_id' => $user->id,
            'reaction' => $data['reaction'],
        ]);

        event(new MessageReactionAdded($message->conversation_id, [
            'message_id' => $message->id,
            'user_id' => $user->id,
            'reaction' => $data['reaction'],
        ]));

        return $reaction;
    }

    public function removeReaction(User $user, array $data): void
    {
        $validator = Validator::make($data, [
            'message_id' => 'required|exists:messages,id',
            'reaction' => 'required|string',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $message = self::messageForMember($user, $data['message_id']);

        $reaction = MessageReaction::where([
            'message_id' => $message->id,
            'user_id' => $user->id,
            'reaction' => $data['reaction'],
        ])->first();

        if (empty($reaction)) {
            throw new ModelNotFoundException("Reaction not found");
        }

        $reaction->delete();

        event(new MessageReactionRemoved($message->conversation_id, [
            'message_id' => $message->id,
            'user_id' => $user->id,
            'reaction' => $data['reaction'],
        ]));
    }

    public function setPinned(User $user, $message_id, bool $pinned): Message
    {
        $message = self::messageForMember($user, $message_id);

        $message->update($pinned ? [
            'is_pinned' => true,
            'pinned_at' => now(),
            'pinned_by' => $user->id,
        ] : [
            'is_pinned' => false,
            'pinned_at' => null,
            'pinned_by' => null,
        ]);

        event($pinned
            ? new MessagePinned($message->conversation_id, ['message_id' => $message->id])
            : new MessageUnpinned($message->conversation_id, ['message_id' => $message->id]));

        return $message->refresh();
    }

    public function bulkMarkRead(User $user, array $data): void
    {
        $validator = Validator::make($data, [
            'conversation_id' => 'required|exists:conversations,id',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        ConversationStateService::memberRow($user, $data['conversation_id']);
        self::markInboundRead($user, $data['conversation_id']);
    }
}
