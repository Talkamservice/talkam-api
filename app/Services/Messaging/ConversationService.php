<?php

namespace App\Services\Messaging;

use App\Constants\General\StatusConstants;
use App\Constants\Messaging\MessagingConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\ConversationReport;
use App\QueryBuilders\Conversation\ConversationQueryBuilder;
use App\Services\User\UserService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ConversationService
{
    protected $message_service;
    protected $user_service;

    public function __construct()
    {
        $this->message_service = new MessageService;
        $this->user_service = new UserService;
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
            // "sender_id" => "bail|nullable|exists:users,id",
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

            $user = auth()->user();

            if ($user->id == $data["receiver_id"]) {
                throw new InvalidRequestException("You can`t chat with yourself");
            }

            $conversation = Conversation::whereHas("members", function ($query) use ($user) {
                $query->whereIn("user_id", [$user->id]);
            })
                ->whereHas("otherMembers", function ($query) use ($data) {
                    $query->whereIn("user_id", [$data["receiver_id"]]);
                })->first();

            if (!empty($conversation)) {
                return $conversation;
            }

            $conversation = Conversation::create([
                "user_id" => $user->id,
                "notification_status" => $data["notification_status"] ?? 1,
                "is_anonymous" => $data["is_anonymous"] ?? 0,
                "status" => $data["status"] ?? StatusConstants::AWAITING_RESPONSE,
            ]);

            $this->addMemberToConversation($user->id, $conversation->id);
            $this->addMemberToConversation($data["receiver_id"], $conversation->id);

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

    public function addMemberToConversation(int $user_id, int $conversation_id)
    {
        return ConversationMember::firstOrCreate([
            "user_id" => $user_id,
            "conversation_id" => $conversation_id,
        ]);
    }

    public function currentConversation(array $data)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($data, [
                "sender_id" => "nullable|exists:users,id",
                "receiver_id" => "required",
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $data = $validator->validated();

            $field = is_numeric($data["receiver_id"]) ? "id" : "username";
            $receiver = $this->user_service->getById($data["receiver_id"], $field);

            $user = auth()->user();

            if ($user->id == $receiver->id) {
                throw new InvalidRequestException("You can`t chat with yourself");
            }

            $conversation = Conversation::whereHas("members", function ($query) use ($user) {
                $query->whereIn("user_id", [$user->id]);
            })
                ->whereHas("otherMembers", function ($query) use ($receiver) {
                    $query->whereIn("user_id", [$receiver->id]);
                })->first();

            if ($conversation?->messages?->isEmpty()) {
                $conversation->delete();
                $conversation = null;
            }

            if (!empty($conversation)) {
                return $conversation;
            }


            $conversation = Conversation::create([
                "user_id" => $user->id,
                "notification_status" => $data["notification_status"] ?? 1,
                "is_anonymous" => $data["is_anonymous"] ?? 0,
                "status" => $data["status"] ?? StatusConstants::AWAITING_RESPONSE,
            ]);

            $this->addMemberToConversation($user->id, $conversation->id);
            $this->addMemberToConversation($receiver->id, $conversation->id);

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

    public static function fetch($conversation_id): Conversation
    {
        $user = auth()->user();

        $conversation = Conversation::whereHas("members", function ($query) use ($user) {
            $query->where("user_id", $user->id);
        })->where("id", $conversation_id)
            ->with("otherMembers")->first();

        return $conversation;
    }


    public static function list(array $data)
    {
        $user = auth()->user();
        $builder = ConversationQueryBuilder::list($data)
            ->whereHas('otherMembers')
            ->with(['lastMessage', 'otherMembers', 'messages' => function ($query) {
                $query->latest();
            }])
            ->withCount([
                'messages as latest_message_timestamp' => function (Builder $query) {
                    $query->select(DB::raw('MAX(created_at)'));
                }
            ])
            ->orderByDesc('latest_message_timestamp');

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
