<?php

namespace App\Http\Controllers\Api\V1\User\Messaging;

use App\Constants\General\ApiConstants;
use App\Constants\General\AppConstants;
use App\Events\NewMessage;
use App\Events\ReceiveMessage;
use App\Events\RefreshMessage;
use App\Events\RefreshNotification;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Messaging\MessageResource;
use App\Notifications\Messaging\NewMessageNotification;
use App\Services\Messaging\ConversationService;
use App\Services\Messaging\MessageService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class MessagingController extends Controller
{
    protected $messaging_service;
    protected $conversation_service;

    public function __construct()
    {
        $this->conversation_service = new ConversationService;
        $this->messaging_service = new MessageService;
    }

    public function list(Request $request)
    {
        try {
            $messaging = $this->messaging_service->list($request->all())->latest()->paginate(AppConstants::API_PAGINATION_SIZE);
            $data = collectPagination($messaging);
            $data["data"] = MessageResource::collection($data["data"]);
            return ApiHelper::validResponse("Messages fetched successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse("Something went wrong while trying to process your request", ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function sendMessage(Request $request)
    {
        try {
            $message = $this->messaging_service->create($request->all());
            
            $conversationId = $message->conversation_id;
            $data = MessageResource::make($message);

            broadcast(new ReceiveMessage($data, $conversationId, $message->receiver_id))->toOthers();
            Notification::send($message->receiver, new NewMessageNotification($message));
            broadcast(new RefreshNotification($message->receiver_id))->toOthers();
            
            return ApiHelper::validResponse("Message sent successfully", MessageResource::make($message));
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (ModelNotFoundException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function deleteMessage(string $id)
    {
        try {
            $this->messaging_service->delete($id);
            return ApiHelper::validResponse("Message deleted successfully");
        } catch (ModelNotFoundException $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        } catch (\Throwable $th) {
            // throw $th;
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }
}
