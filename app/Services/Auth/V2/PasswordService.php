<?php

namespace App\Services\Auth\V2;

use App\Constants\Auth\PinConstants;
use App\Exceptions\Auth\AuthException;
use App\Models\User;
use App\Services\Auth\PasswordService as V1PasswordService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * v2 password flows: 6-digit reset codes; reset requires confirmation,
 * the strength regex, and a password different from the current one.
 * Subclassed (not modified in place) so v1 keeps its 4-digit/lenient behavior.
 */
class PasswordService extends V1PasswordService
{
    const PIN_LENGTH = 6;

    public function sendPasswordResetPin(array $data)
    {
        $validator = Validator::make($data, [
            'email' => 'required|email|exists:users,email',
        ], [
            'email.exists' => "The email address does not exist in our records.",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        $data = $validator->validated();
        $user = User::where($data)->first();
        $pin_expiry = now()->addSeconds(config("system.configuration.pin_expiry"));

        $this->pin_service->create($user, [
            "type" => PinConstants::TYPE_PASSWORD_RESET,
            "expires_at" => $pin_expiry,
            "length" => self::PIN_LENGTH,
            "code_type" => "int",
        ]);
    }

    public function resetPassword(array $data)
    {
        $validator = Validator::make($data, [
            "code" => "required|string",
            'password' => [
                'required',
                'string',
                'confirmed',
                'regex:/' . PinConstants::PASSWORD_REGEX . '/',
            ],
        ], [
            'password.regex' => "The password must be 8-32 characters and contain at least one uppercase letter, one lowercase letter, one number and one special character.",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data = $validator->validated();

        $check = $this->pin_service->verify([
            "code" => $data["code"],
            "type" => PinConstants::TYPE_PASSWORD_RESET
        ]);

        $user = $check["user"];

        if (Hash::check($data["password"], $user->password)) {
            throw new AuthException("Kindly choose a different password from your current one.");
        }

        $user->update([
            "password" => Hash::make($data["password"])
        ]);

        $check["pin"]?->delete();
    }
}
