<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Communication\Mesibo\MesiboService;
use App\Services\Finance\PaymentGateways\Stripe\StripeService;
use App\Services\Notifications\AppMailerService;
use App\Services\Provider\HubspotService;
use App\Services\User\UserService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RegistrationService
{

    public $user_service;

    public function __construct()
    {
        $this->user_service = new UserService;
    }

    public function create(array $data): User
    {
        $user = $this->user_service->create($data);
        return $user;
    }

    public function steps(array $data)
    {
        $validator = Validator::make($data, [
            "field" => "required|string|in:email,password",
            "value" => "required|string"
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // If the field is email , send an OTP to the email address
        if ($data["field"] == "email") {
            $validator = Validator::make(["email" => $data["value"]], [
                "email" => "required|email|unique:users,email",
            ], [
                'email.unique' => "The email address has already been used by another user"
            ]);
            if ($validator->fails()) {
                throw new ValidationException($validator);
            }
            (new VerifyService)->sendPin(new User(["email" => $data["value"]]));
            return "Email verification code sent successfully";
        }

        // If its a password field, verify that the length of the password is 4 digits
        if ($data["field"] == "password") {
            $validator = Validator::make(["password" => $data["value"]], [
                'password' => ['required', 'regex:/^\d{4}$/'],
            ], [
                'password.regex' => 'The password must be a 4-digit number.'
            ]);
            if ($validator->fails()) {
                throw new ValidationException($validator);
            }
            return "Password validated successfully";
        }
    }


    public  function postRegisterActions(User $user)
    {
        $this->registerWithHubspot($user);
        $this->registerUserWithStripe($user);
        $this->registerUserWithMesibo($user);
        $this->sendWelcomeMessage($user);
        $this->notifyAdmin($user);
    }

    public  function postLoginActions(User $user)
    {
        $this->registerUserWithStripe($user);
        $this->registerUserWithMesibo($user);
    }

    public function registerUserWithStripe($user)
    {
        try {
            $response = (new StripeService)->setCustomerData([
                "name" => $user->name,
                "email" => $user->email,
            ])->createCustomer();

            if (!empty($response)) {
                $user->update([
                    "stripe_customer_id" => $response["id"] ?? $user->stripe_customer_id,
                ]);
            }
        } catch (\Throwable $th) {
            //throw $th;
            logger("Stripe Error", [
                "error" =>  $th->getMessage(),
                "thrace" =>  $th->getTrace(),
            ]);
        }
    }

    public function registerUserWithMesibo($user)
    {
        try {
            (new MesiboService)->setUser($user)
                // ->createUserId()
                ->saveUserData();
        } catch (\Throwable $th) {
            //throw $th;
            logger("Mesibo Error", [
                "error" =>  $th->getMessage(),
                "thrace" =>  $th->getTrace(),
            ]);
        }
    }

    public function registerWithHubspot($user)
    {
        try {
            $exists = (new HubspotService)->verifyContact($user->email);

            if (!$exists) {
                (new HubspotService)->setCustomerData([
                    "email" => $user->email,
                    "firstname" => $user->name,
                ])
                    ->createContact();
            }

        } catch (\Throwable $th) {
            //throw $th;
            logger("Hubspot Error", [
                "error" =>  $th->getMessage(),
                "thrace" =>  $th->getTrace(),
            ]);
        }
    }

    private function sendWelcomeMessage(User $user)
    {
        AppMailerService::send([
            "data" => [
                'recipient_name' => $user->name,
                "title" => "Welcome to Mentra",
                "message" => "Welcome to Mentra"
            ],
            "to" => $user->email,
            "template" => "emails.template.v1.welcome.index",
            "subject" => "Welcome to Mentra",
        ]);
    }

    public function notifyAdmin($user) {

    }
}
