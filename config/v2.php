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

    // Robust messaging (planning doc 16) — all data, not code.
    'messaging' => [
        'max_length' => env('V2_MESSAGE_MAX_LENGTH', 1000),
        'edit_window_minutes' => env('V2_MESSAGE_EDIT_WINDOW', 15),
        'delete_window_minutes' => env('V2_MESSAGE_DELETE_WINDOW', 60),
        'file_max_kb' => env('V2_MESSAGE_FILE_MAX_KB', 10240),
        'reaction_max_length' => env('V2_REACTION_MAX_LENGTH', 16),
    ],
];
