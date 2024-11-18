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
            $global_notifications = DatabaseNotification::where('notifiable_id', auth()->user()->id)
                ->where(function ($query) {
                    $query->where('type', 'App\Notifications\Promotion\NewSubscriptionNotification')
                        ->orWhere('type', 'App\Notifications\Promotion\SubscriptionRenewalNotification')
                        ->orWhere('type', 'App\Notifications\Promotion\SubscriptionDisabledNotification')
                        ->orWhere('type', 'App\Notifications\Finance\Payment\AdminNewPaymentNotification');
                })
                ->get();
            $notificationCount = $global_notifications->count();
            return response()->json([
                'success' => true,
                'success_message' => 'All notifications cleared successfully',
                'notifications' => $global_notifications,
                'notification_count' => $notificationCount
            ]);
        } catch (\Throwable $th) {
            return response()->json(['success' => false, 'error_message' => 'Failed to clear notifications.'], 500);
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
            $global_notifications = DatabaseNotification::where('notifiable_id', auth()->user()->id)
                ->where(function ($query) {
                    $query->where('type', 'App\Notifications\Promotion\NewSubscriptionNotification')
                        ->orWhere('type', 'App\Notifications\Promotion\SubscriptionRenewalNotification')
                        ->orWhere('type', 'App\Notifications\Promotion\SubscriptionDisabledNotification')
                        ->orWhere('type', 'App\Notifications\Finance\Payment\AdminNewPaymentNotification');
                })
                ->get();
            $notificationCount = $global_notifications->count();
            return response()->json([
                'success' => true,
                'success_message' => 'Notification deleted successfully',
                'notifications' => $global_notifications,
                'notification_count' => $notificationCount
            ]);
        } catch (ModelNotFoundException $th) {
            return response()->json(['success' => false, 'error_message' => 'Notification not found'], 404);
        } catch (\Throwable $th) {
            return response()->json(['success' => false, 'error_message' => 'Something went wrong.'], 500);
        }
    }
}
