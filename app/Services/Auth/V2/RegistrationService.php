<?php

namespace App\Services\Auth\V2;

use App\Constants\Auth\PinConstants;
use App\Constants\General\AppConstants;
use App\Models\User;
use App\Services\Auth\RegistrationService as V1RegistrationService;
use App\Services\User\UserService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * v2 registration: single full_name field (split via UserService::getNames),
 * required phone_number + username, validated country_id, strength-checked
 * password, and a 6-digit email verification OTP.
 */
class RegistrationService extends V1RegistrationService
{
    public function __construct()
    {
        parent::__construct();
        $this->verify_service = new VerifyService;
    }

    public function create(array $data): User
    {
        $data = $this->validateV2($data);

        $payload = array_merge(UserService::getNames($data["full_name"]), [
            "email" => $data["email"],
            "phone_number" => $data["phone_number"],
            "username" => $data["username"],
            "password" => $data["password"],
            "gender" => $data["gender"] ?? null,
            "date_of_birth" => $data["date_of_birth"] ?? null,
            "fcm_token" => $data["fcm_token"] ?? null,
        ]);

        $user = $this->user_service->create($payload);

        if (!empty($data["country_id"])) {
            $user->update(["country_id" => $data["country_id"]]);
        }

        $this->verify_service->sendPin($user);

        return $user->refresh();
    }

    private function validateV2(array $data): array
    {
        $validator = Validator::make($data, [
            "full_name" => "required|string|min:2|max:150",
            "email" => "required|email|unique:users,email",
            "phone_number" => "required|string|max:30",
            "username" => [
                'required',
                'string',
                'unique:users,username',
                'regex:/^[\w-]*$/',
            ],
            "country_id" => "nullable|exists:countries,id",
            "password" => [
                'required',
                'string',
                'regex:/' . PinConstants::PASSWORD_REGEX . '/',
            ],
            "gender" => Rule::in(AppConstants::GENDERS) . "|nullable",
            "date_of_birth" => 'nullable|date_format:Y-m-d|before:today',
            "fcm_token" => 'nullable|string',
        ], [
            'email.unique' => "The email address has already been used by another user",
            'username.unique' => "The username has already been taken",
            'username.regex' => "The username can only contain letters, numbers, underscores, and dashes, and no spaces",
            'password.regex' => "The password must be 8-32 characters and contain at least one uppercase letter, one lowercase letter, one number and one special character.",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
