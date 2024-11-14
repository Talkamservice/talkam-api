<?php

return [

    'emails' => [
        'sudo' => env("SUDO_EMAIL" , "sudo@talkam.com"),
    ],

    'configuration' => [
        'token_timout' => 60*60*2, //2 hours
        'pin_expiry' => (10 * 60) + 10, // 10 minutes + 10 seconds
    ],
];
