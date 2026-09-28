<?php

namespace App\Constants\Account\User;

class ConsentConstants
{
    const ACCOUNT_OPERATION = 'account_operation';
    const SESSION_DELIVERY = 'session_delivery';
    const ANONYMOUS_COMMUNITY = 'anonymous_community';
    const ANONYMISED_RESEARCH = 'anonymised_research';

    /**
     * Required consents can never be toggled off via the API — withdrawing
     * consent-to-operate is account closure (the existing delete-account flow).
     */
    const REQUIRED_KEYS = [
        self::ACCOUNT_OPERATION,
        self::SESSION_DELIVERY,
    ];

    const OPTIONAL_KEYS = [
        self::ANONYMOUS_COMMUNITY,
        self::ANONYMISED_RESEARCH,
    ];

    const ALL_KEYS = [
        self::ACCOUNT_OPERATION,
        self::SESSION_DELIVERY,
        self::ANONYMOUS_COMMUNITY,
        self::ANONYMISED_RESEARCH,
    ];
}
