<?php

namespace App\Services\Auth;

use App\Exceptions\Auth\AuthException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LoginService
{
    public static function preview($data)
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

        $user = User::where('email', $data["email"])->first();
        return $user;
    }

    public static function authenticate($data)
    {
        $validator = Validator::make($data, [
            'fcm_token' => 'nullable|string',
            'email' => 'required|email|exists:users,email',
            'password' => ['required', 'string'],
        ], [
            'email.exists' => "The email address does not exist in our records.",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        $data = $validator->validated();

        $user = User::where('email', $data["email"])->first();

        if (!Hash::check($data["password"], $user->password)) {
            throw new AuthException("Incorrect password provided.");
        }

        if (!empty($token = $data["fcm_token"] ?? null)) {
            $user->update([
                "fcm_token" => $token
            ]);
        }

        if (empty($user->email_verified_at)) {
            (new VerifyService())->sendPin($user);
        }

        return $user->refresh();
    }

    public static function newLogin(User $user)
    {
        //Todo: Log the user activity
        self::updateLogin($user);
    }

    public static function updateLogin(User $user)
    {
        $user->update([
            "last_login_at" => now(),
        ]);
    }
}
