<?php

/*
|--------------------------------------------------------------------------
| TalkAM v2 lane configuration
|--------------------------------------------------------------------------
*/

return [
    'data_export' => [
        'expiry_days' => env('V2_DATA_EXPORT_EXPIRY_DAYS', 7),
    ],

    // Wellness check-in nudge (planning doc 10): at most once daily, only
    // when no mood check-in exists that day.
    'wellness_nudge' => [
        'hour' => env('V2_WELLNESS_NUDGE_HOUR', 20),
    ],

    /*
    | Mood check-ins (web §02). The factor vocabulary is the deck's chip row on
    | the Check-ins screen — data, so the list is a config fix.
    */
    'checkins' => [
        // The 5-level emoji scale on the check-in modal (MoodConstants::MIN..MAX).
        // Key+label lookup, same shape as 'factors' below — the mobile client
        // was rendering these 5 with a hardcoded label, no server list to match.
        'moods' => [
            ['value' => 1, 'key' => 'very_sad', 'label' => 'Very sad'],
            ['value' => 2, 'key' => 'sad', 'label' => 'Sad'],
            ['value' => 3, 'key' => 'neutral', 'label' => 'Neutral'],
            ['value' => 4, 'key' => 'happy', 'label' => 'Happy'],
            ['value' => 5, 'key' => 'very_happy', 'label' => 'Very happy'],
        ],
        'factors' => [
            ['key' => 'work', 'label' => 'Work'],
            ['key' => 'sleep', 'label' => 'Sleep'],
            ['key' => 'family', 'label' => 'Family'],
            ['key' => 'health', 'label' => 'Health'],
            ['key' => 'money', 'label' => 'Finances'],
            ['key' => 'social', 'label' => 'Relationships'],
            ['key' => 'exercise', 'label' => 'Exercise'],
            ['key' => 'rest', 'label' => 'Rest'],
        ],
        // Days in the trend window the dashboard charts render.
        'trend_days' => env('V2_CHECKIN_TREND_DAYS', 14),
        'note_max_length' => 280,
    ],

    // Robust messaging (planning doc 16) — all data, not code.
    'messaging' => [
        'max_length' => env('V2_MESSAGE_MAX_LENGTH', 1000),
        'edit_window_minutes' => env('V2_MESSAGE_EDIT_WINDOW', 15),
        'delete_window_minutes' => env('V2_MESSAGE_DELETE_WINDOW', 60),
        'file_max_kb' => env('V2_MESSAGE_FILE_MAX_KB', 10240),
        'reaction_max_length' => env('V2_REACTION_MAX_LENGTH', 16),
    ],
];
