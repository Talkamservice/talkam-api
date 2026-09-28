<?php

namespace App\Services\Auth\V2;

use App\Constants\Auth\PinConstants;
use App\Models\User;
use App\Services\Auth\VerifyService as V1VerifyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * v2 verification: identical to v1 except OTP codes are 6 digits.
 * Subclassed (not modified in place) so every v1 call site keeps 4-digit codes.
 */
class VerifyService extends V1VerifyService
{
    const PIN_LENGTH = 6;

    public function sendPin(User $user, string $type = PinConstants::TYPE_VERIFY_EMAIL)
    {
        $pin_expiry = now()->addSeconds(config("system.configuration.pin_expiry"));
        $this->pin_service->create($user, [
            "type" => $type,
            "expires_at" => $pin_expiry,
            "length" => self::PIN_LENGTH,
            "code_type" => "int",
        ]);
    }

    public function request(array $data)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($data, [
                "type" => "required|string|" . Rule::in(array_keys(PinConstants::TITLES)),
                "email" => "required|email",
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $data = $validator->validated();
            $user = User::where("email", $data["email"])->first();

            $pin_expiry = now()->addSeconds(config("system.configuration.pin_expiry"));

            $this->pin_service->create($user, [
                "type" => $data["type"],
                "expires_at" => $pin_expiry,
                "length" => self::PIN_LENGTH,
                "code_type" => "int",
            ]);

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }
}
