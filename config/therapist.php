<?php

/*
|--------------------------------------------------------------------------
| Therapist program configuration
|--------------------------------------------------------------------------
|
| Money and preset values are config-driven by design (planning doc 06):
| the pending product answers (share %, caps, payout day, presets,
| credential list) are data fixes here — never code changes.
| Current values are PLACEHOLDERS pending product sign-off.
|
*/

return [
    'platform_share_percent' => env('THERAPIST_PLATFORM_SHARE', 15),

    'session_rate' => [
        'currency' => 'NGN',
        'min' => env('THERAPIST_RATE_MIN', 15000),
        'max' => env('THERAPIST_RATE_MAX', 20000),
    ],

    'payout_day' => env('THERAPIST_PAYOUT_DAY', 'wednesday'),

    // Web §04 dashboard: analytics range windows (days) and the self-care nudge
    // threshold (sessions in a week before the nudge shows).
    'analytics_windows' => [
        '4w' => 28,
        '12w' => 84,
        '6m' => 182,
    ],
    'self_care_session_threshold' => env('THERAPIST_SELF_CARE_THRESHOLD', 15),

    // Earnings & payouts (planning doc 13)
    'payout' => [
        'minimum' => env('THERAPIST_PAYOUT_MINIMUM', 1000),
    ],

    // Minutes. Standard / Express / Extended labels are client-side.
    'session_durations' => [15, 30, 50],

    // Minutes between sessions ("gives you time to write Notes").
    'buffers' => [0, 10, 15],

    'credential_types' => [
        'Clinical Psychologist',
        'Counselling Psychologist',
        'Psychiatrist',
        'Licensed Therapist',
        'Licensed Clinical Social Worker',
    ],

    'document_max_kb' => 5120,

    // Booking (planning doc 07)
    'booking' => [
        'hold_minutes' => env('THERAPIST_BOOKING_HOLD_MINUTES', 15),
        'reminder_lead_minutes' => env('THERAPIST_REMINDER_LEAD_MINUTES', 30),
    ],

    // Treatment-plan progress vocabulary (planning doc 11) — placeholder
    // pending product's final wording.
    'progress_statuses' => [
        'good_progress',
        'steady',
        'needs_attention',
    ],

    // Sessions (planning doc 08) — all thresholds config-driven.
    'sessions' => [
        'free_cancellation_hours' => env('SESSION_FREE_CANCELLATION_HOURS', 24),
        'reschedule_max_requests' => env('SESSION_RESCHEDULE_MAX', 2),
        'reschedule_cutoff_hours' => env('SESSION_RESCHEDULE_CUTOFF_HOURS', 6),
        'join_early_minutes' => env('SESSION_JOIN_EARLY_MINUTES', 0),
    ],
];
