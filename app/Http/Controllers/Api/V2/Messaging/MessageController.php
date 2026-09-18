<?php

namespace App\Http\Controllers\Api\V2\Messaging;

use App\Constants\General\ApiConstants;
use App\Constants\General\AppConstants;
use App\Events\Messaging\UserTyping;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\MessageHide;
use App\Services\Messaging\V2\ConversationStateService;
use App\Services\Messaging\V2\MessageActionService;
use App\Services\Messaging\V2\MessageSearchService;
use App\Services\User\PrivacySettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Exception;

class MessageController extends Controller
{
    public $action_service;
    function __construct()
    {
        $this->action_service = new MessageActionService;
    }

    /**
     * v2 list: membership-gated, marks inbound read (correctly — v1 bug #2),
     * hides for_me-deleted rows, pinned filter, counterpart read-state
     * suppression per §09.
     */
    public function list(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "conversation_id" => "required|exists:conversations,id",
                "pinned" => "nullable|boolean",
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $viewer = auth()->user();
            $conversation_id = $request->conversation_id;
            ConversationStateService::memberRow($viewer, $conversation_id);

            MessageActionService::markInboundRead($viewer, $conversation_id);

            $hidden_ids = MessageHide::where("user_id", $viewer->id)->pluck("message_id");

            $builder = Message::with("reactions")
                ->where("conversation_id", $conversation_id)
                ->whereNotIn("id", $hidden_ids);

            if (!empty($request->pinned)) {
                $builder = $builder->where("is_pinned", true);
            }

            $messages = $builder->orderBy("id", "desc")
                ->paginate(AppConstants::API_PAGINATION_SIZE)
                ->appends($request->query());

            // Counterpart read-state suppression: for messages the viewer
            // SENT, hide read state when the reader turned receipts off.
            $counterpart = $messages->getCollection()
                ->firstWhere("sender_id", "!=", $viewer->id)?->sender
                ?? $messages->getCollection()->first()?->receiver;
            $counterpart_receipts_on = empty($counterpart)
                || PrivacySettingService::forUser($counterpart)["read_receipts"];

            $data = collectPagination($messages);
            $data["data"] = $messages->getCollection()->map(function ($message) use ($viewer, $counterpart_receipts_on) {
                $is_own = $message->sender_id == $viewer->id;
                $show_read = !$is_own || $counterpart_receipts_on;

                return [
                    "id" => $message->id,
                    "conversation_id" => $message->conversation_id,
                    "sender_id" => $message->sender_id,
                    "receiver_id" => $message->receiver_id,
                    "message" => $message->message,
                    "message_type" => $message->message_type,
                    "file_id" => $message->file_id,
                    "voice_duration" => $message->voice_duration,
                    "delivered_at" => $message->delivered_at?->toDateTimeString(),
                    "read" => $show_read ? (bool) $message->read : null,
                    "read_at" => $show_read ? $message->read_at?->toDateTimeString() : null,
                    "edited_at" => $message->edited_at?->toDateTimeString(),
                    "is_pinned" => (bool) $message->is_pinned,
                    "is_forwarded" => (bool) $message->is_forwarded,
                    "replied_to_message_id" => $message->replied_to_message_id,
                    "reply_count" => $message->reply_count,
                    "reactions" => $message->reactions->map(fn ($r) => [
                        "user_id" => $r->user_id,
                        "reaction" => $r->reaction,
                    ])->values()->all(),
                    "created_at" => $message->created_at->toDateTimeString(),
                ];
            });

            return ApiHelper::validResponse("Messages fetched successfully", $data);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (ModelNotFoundException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::NOT_FOUND_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function send(Request $request)
    {
        try {
            $message = $this->action_service->send(auth()->user(), $request->all());
            return ApiHelper::validResponse("Message sent successfully", $message->toArray());
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (ModelNotFoundException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::NOT_FOUND_ERR_CODE, null, $e);
        } catch (InvalidRequestException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::FORBIDDEN_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function bulkMarkRead(Request $request)
    {
        try {
            $this->action_service->bulkMarkRead(auth()->user(), $request->all());
            return ApiHelper::validResponse("Messages marked as read");
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (ModelNotFoundException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::NOT_FOUND_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function search(Request $request)
    {
        try {
            $results = MessageSearchService::search(auth()->user(), $request->all())
                ->paginate(AppConstants::API_PAGINATION_SIZE)
                ->appends($request->query());

            $data = collectPagination($results);
            $data["data"] = $results->getCollection()->map(fn ($m) => [
                "id" => $m->id,
                "conversation_id" => $m->conversation_id,
                "sender_id" => $m->sender_id,
                "message" => $m->message,
                "message_type" => $m->message_type,
                "created_at" => $m->created_at->toDateTimeString(),
            ]);

            return ApiHelper::validResponse("Search results returned successfully", $data);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function typing(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "conversation_id" => "required|exists:conversations,id",
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $user = auth()->user();
            ConversationStateService::memberRow($user, $request->conversation_id);

            // Suppressed when §09 activity_status is off.
            if (PrivacySettingService::forUser($user)["activity_status"]) {
                event(new UserTyping((int) $request->conversation_id, [
                    "user_id" => $user->id,
                ]));
            }

            return ApiHelper::validResponse("Typing signal sent");
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (ModelNotFoundException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::NOT_FOUND_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
