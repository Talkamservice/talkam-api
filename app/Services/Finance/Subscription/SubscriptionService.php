<?php

namespace App\Services\Finance\Subscription;

use App\Constants\Finance\Payment\PaymentConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\Finance\SubscriptionException;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\Plan;
use App\Models\PlanDuration;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Finance\PaymentGateways\Flutterwave\FlutterwaveService;
use App\Services\Finance\PaymentGateways\Stripe\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class SubscriptionService
{
    public static function getById($id): Subscription
    {
        $subscription = Subscription::find($id);
        if (empty($subscription)) {
            throw new ModelNotFoundException("Subscription not found");
        }
        return $subscription;
    }

    public static function validate(array $data, $id = null): array
    {
        $validator = Validator::make($data, [
            "plan_duration_id" => "required|exists:plan_durations,id",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public function initiate(array $data)
    {
        DB::beginTransaction();
        try {
            $data = self::validate($data);
            $plan_duration = PlanDuration::find($data["plan_duration_id"]);
            self::checkForSubscription(auth()->user(), $plan_duration);
            $response = $this->intiatePayment($plan_duration);
            DB::commit();
            return $response;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public static function checkForSubscription($model, $plan_duration)
    {
        $currentSubscription = self::currentUserSubscription($model, $plan_duration);
        if (!empty($currentSubscription)) {
            throw new InvalidRequestException("You are already subscribed to this plan");
        }
    }

    public static function currentUserSubscription(User $user, PlanDuration $plan_duration)
    {
        return Subscription::where("user_id", $user->id)
            ->where("plan_duration_id", $plan_duration->id)
            ->where("expires_at", ">", now())
            ->whereHas("plan")
            ->with("plan")
            ->where("status", StatusConstants::ACTIVE)
            ->orderby("expires_at", "desc")
            ->first();
    }

    public function initiatePayment(Request $request)
    {
        // Get the authenticated user or from the request
        $user = auth()->user() ?? $request->user;
    
        if (!$user) {
            throw new InvalidRequestException("User not authenticated."); // Explicit error handling for unauthenticated users
        }
    
        // Create customer in Flutterwave if customer ID is empty
        if (empty($user->flutterwave_customer_id)) {
            $response = (new FlutterwaveService)->setCustomerData([
                "name" => $user->name,
                "email" => $user->email,
            ])->createCustomer(); // Assuming this returns a response with the Flutterwave customer ID
    
            if (!empty($response) && isset($response["id"])) {
                $user->update([
                    "flutterwave_customer_id" => $response["id"] // Save Flutterwave customer ID
                ]);
            } else {
                throw new InvalidRequestException("Unable to create Flutterwave customer");
            }
        }
    
        $plan = (new FlutterwaveService)->getById($request->plan_id);
        $durations = $plan->durations;
    
        // Initialize payment intent response variable
        $payment_intent_response = null;
    
        // Process each duration for pricing
        foreach ($durations as $duration) {
            $amount = floatval((new Plan())->parsePlanPrice($duration)); // Ensure you're getting the correct amount
    
            // Create a pricing structure with Flutterwave
            $response = (new FlutterwaveService)->setPriceData([
                'currency' => 'USD',
                'amount' => $amount,
                'plan' => $plan->name,
            ])->createTransaction();
    
            if (!empty($response)) {
                $duration->update([
                    "flutterwave_price_id" => $response["id"] // Save Flutterwave price ID
                ]);
            }
        }
    
    }
    

    

    public static function list()
    {
        $subscriptions = Subscription::status()->latest();
        return $subscriptions;
    }


    public static function subscribeToPlan($user, $plan_duration)
    {
        $subscription =  Subscription::create([
            "user_id" => $user->id,
            "plan_id" => $plan_duration->plan_id,
            "plan_duration_id" => $plan_duration->id,
            "price" => $plan_duration->price,
            "paid_on" => now(),
            "expires_at" => now()->addDays($plan_duration->duration),
            "status" => StatusConstants::ACTIVE
        ]);

        return $subscription;
    }

    public static function cancel($subscription)
    {
        (new StripeService)->cancelSubscription($subscription->stripe_subscription_id);

        $subscription->update([
            "status" => StatusConstants::CANCELLED
        ]);

        return $subscription->refresh();
    }
}
