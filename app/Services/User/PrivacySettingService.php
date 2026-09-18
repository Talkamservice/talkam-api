<?php

namespace App\Services\User;

use App\Constants\Auth\PinConstants;
use App\Exceptions\Auth\PinException;
use App\Models\User;
use App\Models\UserPrivacySetting;
use App\Services\Auth\PinService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PrivacySettingService
{
    /**
     * Documented defaults when no row exists yet.
     */
    const DEFAULTS = [
        'anonymous_mode' => false,
        'read_receipts' => true,
        'activity_status' => true,
        'two_factor_enabled' => false,
    ];

    public static function forUser(User $user): array
    {
        $row = UserPrivacySetting::where('user_id', $user->id)->first();

        return [
            'anonymous_mode' => (bool) ($row->anonymous_mode ?? self::DEFAULTS['anonymous_mode']),
            'read_receipts' => (bool) ($row->read_receipts ?? self::DEFAULTS['read_receipts']),
            'activity_status' => (bool) ($row->activity_status ?? self::DEFAULTS['activity_status']),
            'two_factor_enabled' => (bool) ($row->two_factor_enabled ?? self::DEFAULTS['two_factor_enabled']),
        ];
    }

    public static function twoFactorEnabled(User $user): bool
    {
        return (bool) UserPrivacySetting::where('user_id', $user->id)
            ->value('two_factor_enabled');
    }

    /**
     * Partial updates allowed. Enabling 2FA requires a fresh TYPE_LOGIN OTP
     * (issued via the existing PIN pipeline).
     */
    public function update(User $user, array $data): array
    {
        $validator = Validator::make($data, [
            'anonymous_mode' => 'nullable|boolean',
            'read_receipts' => 'nullable|boolean',
            'activity_status' => 'nullable|boolean',
            'two_factor_enabled' => 'nullable|boolean',
            'otp' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        $enabling_2fa = ($validated['two_factor_enabled'] ?? null) === true
            && !self::twoFactorEnabled($user);

        if ($enabling_2fa) {
            if (empty($validated['otp'])) {
                throw new PinException("A fresh verification code is required to enable two-factor authentication.");
            }

            $check = PinService::verify([
                'code' => $validated['otp'],
                'type' => PinConstants::TYPE_LOGIN,
            ]);

            if (($check['user']?->id ?? null) != $user->id) {
                throw new PinException("The code is invalid. Kindly request a new code.");
            }
        }

        $row = UserPrivacySetting::firstOrCreate(
            ['user_id' => $user->id],
            self::DEFAULTS
        );

        $row->update(array_intersect_key($validated, self::DEFAULTS));

        return self::forUser($user);
    }
}
