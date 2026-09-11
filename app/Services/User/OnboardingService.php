<?php

namespace App\Services\User;

use App\Constants\Account\User\ConsentConstants;
use App\Constants\Account\User\OnboardingConstants;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OnboardingService
{
    /**
     * Derived onboarding progress — computed from state, never stored.
     * The app resumes at the first false step.
     */
    public static function state(User $user): array
    {
        $required_granted = $user->consents()
            ->whereIn('key', ConsentConstants::REQUIRED_KEYS)
            ->where('granted', true)
            ->count() === count(ConsentConstants::REQUIRED_KEYS);

        return [
            'user_type' => $user->user_type,
            'interests' => $user->interests()->count() >= 3,
            'avatar' => !empty($user->avatar),
            'consents' => $required_granted,
            'completed_at' => !empty($user->onboarding_completed_at)
                ? formatDate($user->onboarding_completed_at)
                : null,
        ];
    }

    public function setUserType(User $user, array $data): User
    {
        $validator = Validator::make($data, [
            'user_type' => ['required', Rule::in(OnboardingConstants::USER_TYPES)],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $user->update([
            'user_type' => $validator->validated()['user_type'],
        ]);

        return $user->refresh();
    }

    public function complete(User $user): User
    {
        $user->update([
            'onboarding_completed_at' => now(),
        ]);

        return $user->refresh();
    }
}
