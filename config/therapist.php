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

    'payout_day' => env('THERAPIST_PAYOUT_DAY', 'friday'),

    // Minutes. Standard / Express / Extended labels are client-side.
    'session_durations' => [15, 30, 50],

    // Minutes between sessions ("gives you time to write Notes").
    'buffers' => [0, 10, 15],

    'credential_types' => [
        'Clinical Psychologist',
        'Counselling Psychologist',
        'Psychiatrist',
        'Licensed Therapist',
    ],

    'document_max_kb' => 5120,
];
