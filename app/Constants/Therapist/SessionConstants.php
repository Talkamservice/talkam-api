<?php

namespace App\Constants\Therapist;

class SessionConstants
{
    // Reschedule reasons (design's picker list)
    const REASON_PERSONAL_EMERGENCY = 'personal_emergency';
    const REASON_TECHNICAL_ISSUES = 'technical_issues';
    const REASON_CLIENT_REQUEST = 'client_request';

    const RESCHEDULE_REASONS = [
        self::REASON_PERSONAL_EMERGENCY,
        self::REASON_TECHNICAL_ISSUES,
        self::REASON_CLIENT_REQUEST,
    ];

    // Reschedule request lifecycle
    const RESCHEDULE_PENDING = 'pending';
    const RESCHEDULE_ACCEPTED = 'accepted';
    const RESCHEDULE_DECLINED = 'declined';
    const RESCHEDULE_WITHDRAWN = 'withdrawn';

    // Cancellation actors
    const CANCELLED_BY_CLIENT = 'client';
    const CANCELLED_BY_THERAPIST = 'therapist';

    // Inbound session-request lifecycle (client asks for a preferred time,
    // therapist proposes a real slot from their availability).
    const REQUEST_PENDING = 'pending';
    const REQUEST_PROPOSED = 'proposed';
    const REQUEST_DECLINED = 'declined';
}
