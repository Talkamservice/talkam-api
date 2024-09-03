<?php

namespace App\Http\Controllers\Api\V1\User\Notification;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Notification\NotificationPreferenceResource;
use App\Http\Resources\Notification\NotificationResource;
use App\Models\AppDatabaseNotification;
use App\Models\Message;
use App\Models\User;
use App\Services\Notification\NotificationPreferenceService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class NotificationController extends Controller
{
    protected $notification_preference_service;

    public function __construct()
    {
        $this->notification_preference_service = new NotificationPreferenceService;
    }

    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            $builder = AppDatabaseNotification::where(["notifiable_type" => User::class, "notifiable_id" => $user->id]);

            if (!empty($request->tab) && $request->tab == "mention") {
                $builder = $builder->whereJsonContains('data->type', 'mention');
            }

            $notifications = $builder->latest()->get();
            $data = NotificationResource::collection($notifications);
            return ApiHelper::validResponse("Notifications returned successfully", $data);
        } catch (Exception $e) {
            //throw $th;
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, $request, $e);
        }
    }

    public function show(Request $request, $notification_id)
    {
        try {

            $notification = AppDatabaseNotification::findOrFail($notification_id);
            $notification->markAsRead();
            $data = NotificationResource::make($notification);
            return ApiHelper::validResponse("Notification returned successfully", $data);
        } catch (ModelNotFoundException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, $request, $e);
        } catch (Exception $e) {
            //throw $th;
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, $request, $e);
        }
    }

    public function clearAll(Request $request)
    {
        try {
            $user = auth()->user();
            AppDatabaseNotification::where(["notifiable_type" => User::class, "notifiable_id" => $user->id])->delete();
            return ApiHelper::validResponse("Notification cleared successfully");
        } catch (Exception $e) {
            //throw $th;
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, $request, $e);
        }
    }

    public function markAll(Request $request)
    {
        try {
            $user = auth()->user();
            $notifications = AppDatabaseNotification::where(["notifiable_type" => User::class, "notifiable_id" => $user->id])->get();
            foreach ($notifications as $key => $notification) {
                $notification->markAsRead();
            }
            return ApiHelper::validResponse("Notifications marked as read successfully");
        } catch (Exception $e) {
            //throw $th;
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, $request, $e);
        }
    }

    public function notificationPerference(Request $request)
    {
        try {
            $notification_preference = $this->notification_preference_service->fetch(auth()->id());
            $data = NotificationPreferenceResource::make($notification_preference);
            return ApiHelper::validResponse("Notification preference returned successfully", $data);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function saveNotificationPerference(Request $request)
    {
        try {
            $notification_preference = $this->notification_preference_service->create($request->all());
            $data = NotificationPreferenceResource::make($notification_preference);
            return ApiHelper::validResponse("Notification preference saved successfully", $data);
        } catch (ValidationException $th) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $th);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }

    public function sendThreadNotification(Request $request)
    {
        try {
            $this->notification_preference_service->sendThreadNotification($request->all());
            return ApiHelper::validResponse("Thread notification added successfully");
        } catch (ValidationException $th) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }

    public function notificationStatus(Request $request)
    {
        try {
            $user = !empty($request->user_id) ? User::find($request->user_id) : auth()->user();
            $notifications = $user->notifications;
            $unread_messages = Message::where('receiver_id', $user->id)->where('read', false)->count();
            $data = [
                "notifications" => $notifications->count(),
                "unread_notifications" => $notifications->whereNull("read_at")->count(),
                "unread_messages" => $unread_messages,
            ];
            return ApiHelper::validResponse("Notification stats returned successfully", $data);
        } catch (ValidationException $th) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }
}
