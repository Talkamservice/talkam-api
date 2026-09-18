<?php

namespace App\Services\User;

use App\Constants\Auth\PinConstants;
use App\Exceptions\Auth\PinException;
use App\Exceptions\General\InvalidRequestException;
use App\Models\User;
use App\Services\Auth\PinService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * 4-digit payment-authorization PIN ("Wallet PIN" in the design). There is
 * no wallet balance — the PIN only authorizes saved-card charges.
 */
class PaymentPinService
{
    public function setOrChange(User $user, array $data): void
    {
        $validator = Validator::make($data, [
            'pin' => 'required|digits:4',
            'current_pin' => 'nullable|digits:4',
            'otp' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        if (!empty($user->payment_pin)) {
            $current_ok = !empty($validated['current_pin'])
                && Hash::check($validated['current_pin'], $user->payment_pin);

            $otp_ok = false;
            if (!$current_ok && !empty($validated['otp'])) {
                $check = PinService::verify([
                    'code' => $validated['otp'],
                    'type' => PinConstants::TYPE_LOGIN,
                ]);
                $otp_ok = ($check['user']?->id ?? null) == $user->id;
            }

            if (!$current_ok && !$otp_ok) {
                throw new InvalidRequestException("Your current PIN (or a fresh verification code) is required to change the payment PIN.");
            }
        }

        $user->update(['payment_pin' => Hash::make($validated['pin'])]);
    }

    public static function verifyPin(User $user, ?string $pin): bool
    {
        return !empty($user->payment_pin)
            && !empty($pin)
            && Hash::check($pin, $user->payment_pin);
    }
}
