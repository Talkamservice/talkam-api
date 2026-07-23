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
];
