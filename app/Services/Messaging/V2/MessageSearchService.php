<?php

namespace App\Services\Messaging\V2;

use App\Models\ConversationMember;
use App\Models\Message;
use App\Models\MessageHide;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class MessageSearchService
{
    /**
     * Filtered search, strictly scoped to the caller's conversations.
     */
    public static function search(User $user, array $data)
    {
        $validator = Validator::make($data, [
            'q' => 'required|string|min:2',
            'conversation_id' => 'nullable|exists:conversations,id',
            'message_type' => 'nullable|string',
            'sender_id' => 'nullable|exists:users,id',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $filters = $validator->validated();

        $own_conversation_ids = ConversationMember::where('user_id', $user->id)
            ->pluck('conversation_id');
        $hidden_ids = MessageHide::where('user_id', $user->id)->pluck('message_id');

        $builder = Message::whereIn('conversation_id', $own_conversation_ids)
            ->whereNotIn('id', $hidden_ids)
            ->where('message', 'LIKE', '%' . $filters['q'] . '%');

        if (!empty($filters['conversation_id'] ?? null)) {
            $builder = $builder->where('conversation_id', $filters['conversation_id']);
        }
        if (!empty($filters['message_type'] ?? null)) {
            $builder = $builder->where('message_type', $filters['message_type']);
        }
        if (!empty($filters['sender_id'] ?? null)) {
            $builder = $builder->where('sender_id', $filters['sender_id']);
        }
        if (!empty($filters['from'] ?? null)) {
            $builder = $builder->whereDate('created_at', '>=', $filters['from']);
        }
        if (!empty($filters['to'] ?? null)) {
            $builder = $builder->whereDate('created_at', '<=', $filters['to']);
        }

        return $builder->latest();
    }
}
