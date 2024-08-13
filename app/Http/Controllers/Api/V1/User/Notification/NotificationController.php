<?php

namespace App\Http\Controllers\Api\V1\User\Notification;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Notification\NotificationPreferenceResource;
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
}
