<?php

namespace App\Services\Auth;

use App\Constants\Auth\PinConstants;
use App\Exceptions\Auth\PinException;
use App\Helpers\MethodsHelper;
use App\Models\Pin;
use App\Models\User;
use App\Services\Notifications\AppMailerService;
use Carbon\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PinService
{

    public  function create(User $user, array $data)
    {
        $validator = Validator::make($data, [
            "type" => "required|string|" . Rule::in(array_keys(PinConstants::TITLES)),
            "expires_at" => "required|date",
            "length" => "required|numeric",
            "code_type" => "required|in:int,string",
        ]);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data = $validator->validated();
        $query = ['type' => $data["type"]];
        if (!empty($user?->id)) {
            $query["user_id"] = $user->id;
        } else {
            $query["email"] = $user->email;
        }
        $pin = Pin::updateOrCreate($query, [
            'code' => MethodsHelper::generateRandomDigits($data["length"]),
            'expires_at' => $data["expires_at"],
        ]);

        [$template, $mailData] = $this->buildMailPayload($user, $pin, $data);

        AppMailerService::send([
            "data" => $mailData,
            "to" => $user->email,
            "template" => $template,
            "subject" => PinConstants::TITLES[$data["type"]],
        ]);

        return $pin;
    }

    /**
     * Picks the email template + data for a pin's type. TYPE_LOGIN and
     * TYPE_VERIFY_EMAIL (mobile) share one OTP-grid template; business domain
     * verification gets its own; password reset gets a signed one-click link
     * instead of a code (the mobile app's own "enter the code" screen keeps
     * working unchanged — this only changes what the *email* shows).
     */
    private function buildMailPayload(User $user, Pin $pin, array $data): array
    {
        return match ($data["type"]) {
            PinConstants::TYPE_PASSWORD_RESET => [
                'emails.mobile.auth-password-reset',
                [
                    'resetPasswordUrl' => URL::temporarySignedRoute(
                        'password.reset.form',
                        Carbon::parse($data["expires_at"]),
                        ['email' => $user->email, 'code' => $pin->code],
                    ),
                ],
            ],
            PinConstants::TYPE_VERIFY_EMAIL_BUSINESS => [
                'emails.business.auth-domain-verification',
                ['otpDigits' => str_split($pin->code)],
            ],
            default => [
                'emails.mobile.auth-verification-code',
                ['otpDigits' => str_split($pin->code)],
            ],
        };
    }


    public static function verify(array $data)
    {
        $validator = Validator::make($data, [
            "code" => "required|string",
            "type" => "required|string",
        ]);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        $data = $validator->validated();
        $pin = Pin::where($data)->first();

        if (empty($pin)) {
            throw new PinException("The code is invalid. Kindly request a new code.");
        }

        if (!empty($ex = $pin->expires_at) && Carbon::parse($ex)->isPast()) {
            $pin->delete();
            throw new PinException("Code has expired, kindly request a new code");
        }

        $user = $pin->user;

        if ($data["type"] != PinConstants::TYPE_PASSWORD_RESET) {
            $pin->delete();
        }

        return [
            "user" => $user,
            "pin" => $pin
        ];
    }
}
