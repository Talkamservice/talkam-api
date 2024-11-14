<?php

namespace App\Services\Auth;

use App\Constants\Auth\PinConstants;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PasswordService
{
    public $pin_service;
    function __construct()
    {
        $this->pin_service = new PinService;
    }

    public function sendPasswordResetPin(array $data)
    {
        $validator = Validator::make($data,[
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
            "length" => 4,
            "code_type" => "int",
        ]);
    }


    public function resetPassword(array $data)
    {
        $validator = Validator::make($data, [
            "code" => "required|string",
            'password' => 'required',
            "type" => "nullable"
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
        $user->update([
            "password" => Hash::make($data["password"])
        ]);
    }
}
