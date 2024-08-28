<?php

namespace App\QueryBuilders\Conversation;

use App\Constants\General\StatusConstants;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;

class ConversationQueryBuilder
{
    public static function list(array $data = [])
    {
        $builder = Conversation::whereHas("otherMembers")
            ->with("otherMembers");

        if (!empty($key = $data["conversation_id"] ?? null)) {
            $builder = $builder->where("id", $key);
        }

        if (!empty($key = $data["status"] ?? null)) {
            $builder = $builder->where("status", $key);
        }

        if (!empty($key = $data["search"] ?? null)) {
            $builder = $builder->search($key);
        }

        $user = auth()->user();
        if (!empty($key = $data["tab"] ?? null)) {
            $builder = $builder->where("status", StatusConstants::AWAITING_RESPONSE)
                ->whereNot('user_id', $user->id);
        } else {
            $builder->where("user_id", $user->id)
                ->orWhereHas("members", function ($query) use ($user) {
                    $query->where("user_id", $user->id);
                })->whereNot("status", StatusConstants::AWAITING_RESPONSE);
        }

        return $builder;
    }

    public static function listMemberUsers($conversation_id)
    {
        $builder = User::whereHas("conversationMember", function ($query) use ($conversation_id) {
            $query->where("conversation_id", $conversation_id);
        });
        return $builder;
    }


    public static function listMessages(array $data = [])
    {
        $builder = Message::whereHas("conversation");

        if (!empty($key = $data["conversation_id"] ?? null)) {
            $builder = $builder->where("conversation_id", $key);
        }

        // if (!empty($column = $request->order_column) && !empty($by = $request->order_by)) {
        //     $builder = $builder->orderby($column, $by);
        // } else {
        //     $builder = $builder->latest();
        // }

        $builder = $builder->orderby('created_at', 'ASC');

        return $builder;
    }
}