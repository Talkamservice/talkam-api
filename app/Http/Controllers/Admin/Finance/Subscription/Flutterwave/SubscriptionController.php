<?php

namespace App\Http\Controllers\Admin\Finance\Subscription\Flutterwave;

use App\Constants\General\NotificationConstants;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Finance\Subscription\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SubscriptionController extends Controller
{

    public $subscription_service;

    public function __construct(SubscriptionService $subscription_service)
    {
        $this->subscription_service = $subscription_service;
    }
    public function initiateSubscription(Request $request)
    {
        
        try {
            $this->subscription_service->initiate($request->all());
            return back()->with(NotificationConstants::SUCCESS_MSG, "Subcription made successfully");
        } catch (ValidationException $th) {
            throw $th;
        } catch (\Throwable $th) {
            throw $th;
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }

    public function getSubscriber(Request $request)
    {
        dd($request->all());
        $query = $request->get('q', '');
        // Check if there's a valid search term
        if (strlen($query) < 1) {
            // Return an empty array if no search term is provided
            return response()->json([]);
        }

        // Search for users by first name, last name, middle name, or email
        $users = User::where('first_name', 'like', "%{$query}%")
            ->orWhere('last_name', 'like', "%{$query}%")
            ->orWhere('middle_name', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->limit(10) // Limit the number of results
            ->get();

        // Format the result to return id and name
        return response()->json($users->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->getName(),
            ];
        }));
    }
}
