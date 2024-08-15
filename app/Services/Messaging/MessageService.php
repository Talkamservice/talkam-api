<?php

namespace App\Services\Messaging;

use App\Exceptions\General\ModelNotFoundException;
use App\Models\Message;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class MessageService
{
    public static function getById($id): Message
    {
        $message = Message::find($id);
        if (empty($message)) {
            throw new ModelNotFoundException("Message not found");
        }
        return $message;
    }

    public static function validate($data, $id = null)
    {
        $validator = Validator::make($data, [
            "conversation_id" => "bail|required|exists:conversations,id",
            "sender_id" => "bail|nullable|exists:users,id",
            "receiver_id" => "bail|required|exists:users,id",
            "message" => "bail|nullable|string",
            "message_type" => "bail|nullable|string",
            "asset_url" => "bail|nullable|string",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        return $validator->validated();
    }

    public static function create(array $data)
    {
        $data = self::validate($data);
        $data["sender_id"] ??= auth()->id();
        $message = Message::create($data);
        return $message;
    }

    public static function update(array $data, $id)
    {
        $data = self::validate($data, $id);
        $message = self::getById($id);
        $message->update($data);
        return $message->refresh();
    }

    public static function delete($message_id)
    {
        $message = self::getById($message_id);
        $message->delete();
    }


    public static function list(array $data = [])
    {
        $messages = Message::query();

        if (!empty($key = $data["conversation_id"] ?? null)) {
            $messages = $messages->where("conversation_id", $key);
        }


        return $messages;
    }
}
