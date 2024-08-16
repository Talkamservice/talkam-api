<?php

namespace App\Services\Messaging;

use App\Constants\General\StatusConstants;
use App\Constants\Messaging\MessagingConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\Conversation;
use App\Models\ConversationReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ConversationService
{
    protected $message_service;

    public function __construct()
    {
        $this->message_service = new MessageService;
    }

    public static function getById($id): Conversation
    {
        $conversation = Conversation::find($id);
        if (empty($conversation)) {
            throw new ModelNotFoundException("Conversation not found");
        }
        return $conversation;
    }

    public static function validate($data, $id = null)
    {
        $validator = Validator::make($data, [
            "sender_id" => "bail|nullable|exists:users,id",
            "receiver_id" => "bail|nullable|exists:users,id|" . Rule::requiredIf(empty($id)),
            "message" => "bail|nullable|string",
            "message_type" => "bail|nullable|string",
            "asset_url" => "bail|nullable|string",
            "notification_status" => "bail|nullable|in:0,1",
            "is_anonymous" => "bail|nullable|in:0,1",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        return $validator->validated();
    }

    public function create(array $data)
    {
        DB::beginTransaction();
        try {
            $data = self::validate($data);

            $data["sender_id"] ??= auth()->id();

            $conversation = Conversation::where(function ($query) use ($data) {
                $query->where('sender_id', $data["sender_id"])
                    ->where('receiver_id', $data["receiver_id"]);
            })->orWhere(function ($query) use ($data) {
                $query->where('sender_id', $data["receiver_id"])
                    ->where('receiver_id', $data["sender_id"]);
            })->first();

            if (empty($conversation)) {
                $conversation = Conversation::firstOrCreate([
                    "sender_id" => $data["sender_id"],
                    "receiver_id" => $data["receiver_id"]
                ], [
                    "notification_status" => $data["notification_status"] ?? 1,
                    "is_anonymous" => $data["is_anonymous"] ?? 0,
                    "status" => $data["status"] ?? StatusConstants::AWAITING_RESPONSE,
                ]);
            }

            $this->message_service->create([
                "conversation_id" => $conversation->id,
                ...$data
            ]);

            DB::commit();

            return $conversation;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function currentConversation(array $data)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($data, [
                "sender_id" => "nullable|exists:users,id",
                "receiver_id" => "required|exists:users,id",
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $data = self::validate($data);
            $data["sender_id"] ??= auth()->id();

            $conversation = Conversation::where(function ($query) use ($data) {
                $query->where('sender_id', $data["sender_id"])
                    ->where('receiver_id', $data["receiver_id"]);
            })->orWhere(function ($query) use ($data) {
                $query->where('sender_id', $data["receiver_id"])
                    ->where('receiver_id', $data["sender_id"]);
            })->first();

            if (empty($conversation)) {
                $conversation = Conversation::firstOrCreate([
                    "sender_id" => $data["sender_id"],
                    "receiver_id" => $data["receiver_id"]
                ], [
                    "notification_status" => $data["notification_status"] ?? 1,
                    "is_anonymous" => $data["is_anonymous"] ?? 0,
                    "status" => $data["status"] ?? StatusConstants::AWAITING_RESPONSE,
                ]);
            }

            DB::commit();
            return $conversation;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }


    public function update(array $data, $id)
    {
        DB::beginTransaction();
        try {
            $data = self::validate($data, $id);
            $conversation = self::getById($id);
            $conversation->update($data);
            DB::commit();
            return $conversation->refresh();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public static function delete($conversation_id)
    {
        $conversation = self::getById($conversation_id);
        $conversation->delete();
    }

    public static function show($conversation_id)
    {
        $conversation = self::getById($conversation_id);
        $conversation->messages()->where("receiver_id", $conversation->receiver_id)
            ->update(['read' => true]);
        return $conversation->refresh();
    }

    public static function validateStatusUpdate($data, $id = null)
    {
        $validator = Validator::make($data, [
            "conversation_id" => "bail|required|exists:conversations,id",
            "status" => "bail|required|string|" . Rule::in(MessagingConstants::CONVERSATION_ACTIONS),
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        return $validator->validated();
    }

    public function updateStatus(array $data)
    {
        $data = self::validateStatusUpdate($data);
        $conversation = self::getById($data["conversation_id"]);

        $conversation->update([
            "status" => $data["status"]
        ]);

        return $conversation->refresh();
    }

    public static function list(array $data)
    {
        $builder = Conversation::with(["receiver", "sender"]);

        $data["sender_id"] ??= auth()->id();

        if (!empty($key = $data["tab"] ?? null)) {
            $builder = $builder->where('receiver_id', $data["sender_id"]);
        } else {
            $builder = $builder->where(function ($query) use ($data) {
                $query->where('sender_id', $data["sender_id"]);
            })->orWhere(function ($q) use ($data) {
                $q->where('receiver_id', $data["sender_id"])
                    ->whereNot("status", StatusConstants::AWAITING_APPROVAL);
            });
        }

        if (!empty($key = $data["status"] ?? null)) {
            $builder = $builder->where("status", $key);
        }

        if (!empty($searchTerm = $data["search"] ?? null)) {
            $builder->where(function ($q) use ($searchTerm) {
                $q->whereHas('receiver', function ($receiver) use ($searchTerm) {
                    $receiver->search($searchTerm);
                })->orWhereHas("messages", function ($message) use ($searchTerm) {
                    $message->search($searchTerm);
                });
            });
        }

        return $builder;
    }

    public static function report($data)
    {
        $validator = Validator::make($data, [
            "conversation_id" => "required|numeric|exists:messages,id",
            "message_id" => "nullable|numeric|exists:messages,id",
            "reason" => "required|string",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        $data = $validator->validated();
        $data["user_id"] = auth()->id();

        $report = ConversationReport::firstOrCreate([
            "user_id" => $data["user_id"],
            "conversation_id" => $data["conversation_id"]
        ], $data);

        return $report;
    }
}
