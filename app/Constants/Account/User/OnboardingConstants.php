<?php

namespace App\Constants\Account\User;

class OnboardingConstants
{
    /**
     * users.user_type records onboarding intent only — role stays "User".
     * A therapist role is assigned by the (separate) application flow.
     */
    const USER_TYPE_SUPPORT_SEEKER = 'support_seeker';
    const USER_TYPE_MENTAL_HEALTH_PRO = 'mental_health_pro';

    const USER_TYPES = [
        self::USER_TYPE_SUPPORT_SEEKER,
        self::USER_TYPE_MENTAL_HEALTH_PRO,
    ];
}
