<?php

namespace App\Services\Finance\Subscription;

use App\Constants\Finance\Payment\PaymentConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\Finance\SubscriptionException;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Exceptions\Payment\FlutterwaveException;
use App\Models\Plan;
use App\Models\PlanDuration;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Finance\PaymentGateways\Flutterwave\FlutterwaveService;
use App\Services\Finance\PaymentGateways\Stripe\StripeService;
use App\Services\Finance\Plan\PlanService;
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
            $response = $this->initiatePayment($plan_duration);
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
        // Step 1: Get the user either from request or auth
        $user = $request->has('user_id') ? User::find($request->user_id) : auth()->user();
        if (!$user) {
            throw new InvalidRequestException("User not authenticated.");
        }

        // Create Flutterwave customer if necessary
        $flutterwave = new FlutterwaveService();
       
        if (empty($user->flutterwave_customer_id)) {
            // Create a customer in Flutterwave
            $response = $flutterwave->setCustomerData([
                'email' => $user->email,
            ]);
            $response->createCustomer();
// dd($response);
            if (!empty($response) && isset($response['id'])) {
                // Update user with Flutterwave customer ID
                $user->update(['flutterwave_customer_id' => $response['id']]);
            } else {
                dd($response);
                throw new InvalidRequestException("Unable to create Flutterwave customer.");
            }
        }

        //Retrieve plan and durations
        $plan = $flutterwave->getPlanById($request->plan_id);
        $durations = $plan->durations;

        // Process each duration for pricing and create transactions
        foreach ($durations as $duration) {
            $amount = floatval((new PlanService)->parsePlanPrice($duration)); // Get the correct amount

            // Step 5: Create a Flutterwave transaction
            $transaction_data = [
                'tx_ref' => uniqid('tx_'),  // Unique transaction reference
                'redirect_url' => route('admin.payments.flutterwave.callback'),
                'currency' => 'USD',
                'amount' => $amount,
                'customer' => [
                    'email' => $user->email,
                    'id' => $user->flutterwave_customer_id
                ],
                'meta' => [
                    'plan_name' => $plan->name,
                    'duration' => $duration->name,
                ]
            ];

            $response = $flutterwave->setTransactionData($transaction_data)->createTransaction();

            if (!empty($response)) {
                $duration->update([
                    'flutterwave_price_id' => $response['id'] // Save Flutterwave price ID for each duration
                ]);
            } else {
                throw new FlutterwaveException("Transaction creation failed for duration {$duration->name}.");
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
