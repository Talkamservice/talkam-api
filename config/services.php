<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'tiktok' => [
        'client_id' => env('TIKTOK_CLIENT_ID'),
        'client_secret' => env('TIKTOK_CLIENT_SECRET'),
        'redirect' => env('TIKTOK_REDIRECT_URI')
    ],

    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID'),
        'client_secret' => env('APPLE_CLIENT_SECRET'),
        'redirect' => env('APPLE_REDIRECT_URI')
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI')
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI')
    ],

    'firebase' => [
        'clientId' => env('FIREBASE_CLIENT_ID'),
        'clientEmail' => env('FIREBASE_CLIENT_EMAIL'),
        'privateKey' => env('FIREBASE_CLIENT_PRIVATE_KEY'),
    ],

    'flutterwave' => [
        'baseUrl' => env('FLW_BASE_URL'),
        'publicKey' => env('FLW_PUBLIC_KEY'),
        'secretKey' => env('FLW_SECRET_KEY'),
        'secretHash' => env('FLW_SECRET_HASH'),
        // Where Flutterwave sends the browser/webview after a hosted
        // checkout completes. Confirmation itself never trusts this redirect
        // — it's only the client's cue to stop showing the checkout page —
        // the callback webhook is what actually confirms the booking.
        'redirectUrl' => env('FLW_REDIRECT_URL', env('APP_URL') . '/payment/complete'),
        // A callback firing right after checkout can beat Flutterwave's own
        // tx_ref search index — verifyTransactionByReference briefly comes
        // back empty for a transaction that genuinely just succeeded. Retry
        // a few times before giving up on the payment.
        'verifyRetries' => (int) env('FLW_VERIFY_RETRIES', 3),
        'verifyRetryDelayMs' => (int) env('FLW_VERIFY_RETRY_DELAY_MS', 1000),
    ],

    'agora' => [
        'app_id' => env('AGORA_APP_ID'),
        'certificate' => env('AGORA_APP_CERTIFICATE'),
        'webhook_secret' => env('AGORA_WEBHOOK_SECRET'),
    ],
];
