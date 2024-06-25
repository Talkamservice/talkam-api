<?php

namespace App\Services\Auth;

use App\Constants\Auth\PinConstants;
use App\Exceptions\Auth\PinException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class VerifyService
{
    public $pin_service;
    function __construct()
    {
        $this->pin_service = new PinService;
    }

    public function sendPin(User $user)
    {
        $pin_expiry = now()->addSeconds(config("system.configuration.pin_expiry"));
        $this->pin_service->create($user, [
            "type" => PinConstants::TYPE_VERIFY_EMAIL,
            "expires_at" => $pin_expiry,
            "length" => 4,
            "code_type" => "int",
        ]);
    }


    public function verify(array $data)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($data, [
                "code" => "required|string",
                "email" => [auth()->check() ? "nullable" : "required", "email"],
                "type" => "nullable"
            ]);
            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $data = $validator->validated();
            $data["type"] = $data["type"] ?? PinConstants::TYPE_VERIFY_EMAIL;
            $check = $this->pin_service->verify($data);
            $pin = $check["pin"];
            $user = $check["user"] ?? null;

            if (auth()->check() && !empty($user)) {
                if ($user->id != auth()->id()) {
                    throw new PinException("The code is invalid. Kindly request a new code.");
                }
            } else {
                if ($pin?->user?->email != $data["email"]) {
                    throw new PinException("The email address does not match the code. Kindly request a new code.");
                }
            }

            if ($data["type"] == PinConstants::TYPE_VERIFY_EMAIL) {
                $user?->update([
                    "email_verified_at" => now(),
                ]);
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }
}
