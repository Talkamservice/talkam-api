<?php

namespace App\Http\Controllers\Admin\Notification;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function clearAll(Request $request)
    {
        try {
            $user = auth()->user();
            DatabaseNotification::where(["notifiable_type" => User::class, "notifiable_id" => $user->id])->delete();
            return ApiHelper::validResponse("Notification cleared successfully");
        } catch (\Throwable $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE,  $request, $th);
        }
    }

    public function markAll(Request $request)
    {
        try {
            $user = auth()->user();
            $notifications = DatabaseNotification::where(["notifiable_type" => User::class, "notifiable_id" => $user->id])->get();
            foreach ($notifications as $key => $notification) {
                $notification->markAsRead();
            }
            return ApiHelper::validResponse("Notifications marked as read successfully");
        } catch (\Throwable $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE,  $request, $th);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $notification = DatabaseNotification::findOrFail($id);
            $notification->delete();
            return ApiHelper::validResponse("Notification deleted successfully");
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse("Notification does not exist on our record", ApiConstants::BAD_REQ_ERR_CODE, $request, $th);
        } catch (\Throwable $th) {
            return ApiHelper::problemResponse("Something went wrong while trying to process your request.", ApiConstants::SERVER_ERR_CODE, $request, $th);
        }
    }
}
