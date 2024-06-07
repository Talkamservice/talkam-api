<?php

namespace App\Services\Auth;

use App\Constants\Auth\PinConstants;
use App\Exceptions\Auth\PinException;
use App\Helpers\MethodsHelper;
use App\Models\Pin;
use App\Models\User;
use App\Services\Notifications\AppMailerService;
use Carbon\Carbon;
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

        AppMailerService::send([
            "data" => [
                "pin" => $pin,
                'user' => $user,
                "expires_at" => Carbon::parse($data["expires_at"])->diffForHumans()
            ],
            "to" => $user->email,
            "template" => "emails.template.v1.auth.pin." . $data["type"],
            "subject" => PinConstants::TITLES[$data["type"]],
        ]);
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
        $pin = Pin::where($data)->whereHas("user")->with("user")
            ->first();

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
