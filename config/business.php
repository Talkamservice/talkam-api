<?php

/*
|--------------------------------------------------------------------------
| TalkAM for Business configuration
|--------------------------------------------------------------------------
|
| Every money value the B2B onboarding screens render comes from here, so
| final pricing is a data fix and never a code change — same posture as
| config/therapist.php. Values transcribed from "TalkAM B2B Auth.dc.html".
|
*/

return [
    'currency' => 'NGN',

    /*
    | Root of the B2B web dashboard — invite links and email deep links are
    | built from it. Separate from app.web_url, which points at the v1
    | community web app.
    */
    'web_url' => env('BUSINESS_WEB_URL', 'https://talkam.net'),

    /*
    | Volume pricing tiers. `max => null` means "and above". Matches
    | SEAT_TIERS in talkam-web/src/fakedata/v2/auth.js.
    */
    'seat_tiers' => [
        ['min' => 1,   'max' => 100,  'price' => 7000],
        ['min' => 101, 'max' => 300,  'price' => 6000],
        ['min' => 301, 'max' => 500,  'price' => 5500],
        ['min' => 501, 'max' => null, 'price' => 5000],
    ],

    /* Flat monthly line items, per employee seat. */
    'employee_seat_rate' => env('BUSINESS_EMPLOYEE_SEAT_RATE', 2000),
    'therapist_access_rate' => env('BUSINESS_THERAPIST_ACCESS_RATE', 3500),

    /* Per-session drawdown rate for pre-purchased bundles. */
    'session_rate' => env('BUSINESS_SESSION_RATE', 8000),

    /*
    | Blended-pricing inputs. `network_average_rate` here is only the
    | FALLBACK — OrganizationPricingService computes the live mean of
    | approved therapists' session_rate and uses this when the bench is empty.
    */
    'standard_therapist_rate' => env('BUSINESS_STANDARD_THERAPIST_RATE', 15000),
    'network_average_rate' => env('BUSINESS_NETWORK_AVERAGE_RATE', 15450),

    'bundle_options' => [
        ['key' => '10', 'sessions' => 10, 'tag' => null],
        ['key' => '25', 'sessions' => 25, 'tag' => 'Most popular'],
        ['key' => '50', 'sessions' => 50, 'tag' => null],
    ],

    'plan' => [
        'name' => 'Wellbeing Lite',
        'features' => [
            'Unlimited anonymous community access on mobile',
            'Subsidised 1:1 therapy sessions (video, voice, chat)',
            'Anonymised utilisation dashboard, refreshed daily',
            'Monthly PDF report, board-ready',
            'Up to 2 HR admin seats',
            'Dedicated onboarding support',
        ],
    ],

    'pay_methods' => ['invoice', 'card'],

    /*
    | PLACEHOLDER — deck values. Finance must confirm before release.
    */
    'bank_details' => [
        'company' => env('BUSINESS_BANK_COMPANY', 'TalkAM Ltd'),
        'account_name' => env('BUSINESS_BANK_ACCOUNT_NAME', 'TalkAM Technologies Ltd'),
        'bank' => env('BUSINESS_BANK_NAME', 'GTBank'),
        'account_number' => env('BUSINESS_BANK_ACCOUNT_NUMBER', '0123456789'),
    ],

    /*
    | Invited-member onboarding chips.
    |
    | The web B2B "Topics of interest" screen shows exactly these six, in this
    | order (deck "TalkAM B2B Auth.dc.html"). They are a SUBSET of the shared
    | interest-topic taxonomy seeded by InterestTopicSeeder — resolved to
    | post_categories rows by name at request time, so selections land in the
    | same user_interests table the mobile app reads.
    */
    'onboarding_topics' => [
        ['key' => 'anxiety', 'label' => 'Anxiety', 'category' => 'Anxiety'],
        ['key' => 'depression', 'label' => 'Depression', 'category' => 'Depression'],
        ['key' => 'relationships', 'label' => 'Relationships', 'category' => 'Relationships'],
        ['key' => 'work', 'label' => 'Work Stress', 'category' => 'Work Stress'],
        ['key' => 'grief', 'label' => 'Grief', 'category' => 'Grief'],
        ['key' => 'general', 'label' => 'General Support', 'category' => 'General Support'],
    ],

    /*
    | Therapist-bench specialties an admin can prioritise (bench screen).
    | Free-form keys — they shape onboarding priority, not access.
    */
    'bench_topics' => [
        ['key' => 'anxiety', 'label' => 'Anxiety'],
        ['key' => 'depression', 'label' => 'Depression'],
        ['key' => 'relationships', 'label' => 'Relationships'],
        ['key' => 'work', 'label' => 'Work Stress'],
        ['key' => 'grief', 'label' => 'Grief'],
        ['key' => 'trauma', 'label' => 'PTSD / Trauma'],
    ],

    /*
    | Self check-in questionnaire. Categories must match
    | OrganizationConstants::SELF_CHECK_CATEGORIES.
    */
    'self_check' => [
        'questions' => [
            ['category' => 'work', 'label' => 'Work-related stress'],
            ['category' => 'anxiety', 'label' => 'Anxiety or worry'],
            ['category' => 'sleep', 'label' => 'Sleep or low mood'],
            ['category' => 'relationships', 'label' => 'Relationships or family'],
        ],
        'options' => [
            ['value' => 0, 'label' => 'Not at all'],
            ['value' => 1, 'label' => 'Mild'],
            ['value' => 2, 'label' => 'Moderate'],
            ['value' => 3, 'label' => 'Significant'],
        ],
    ],

    'invitations' => [
        /* Deck: "Auto-reminder after 7 days". */
        'expiry_days' => env('BUSINESS_INVITE_EXPIRY_DAYS', 7),
        'csv_max_kilobytes' => 5120,
        'max_per_request' => 500,
    ],

    /*
    | Business signup requires a company domain. Free consumer providers are
    | rejected outright ("free email providers (Gmail, Yahoo) aren't accepted").
    */
    'blocked_email_domains' => [
        'gmail.com',
        'googlemail.com',
        'yahoo.com',
        'yahoo.co.uk',
        'ymail.com',
        'hotmail.com',
        'outlook.com',
        'live.com',
        'msn.com',
        'icloud.com',
        'me.com',
        'aol.com',
        'protonmail.com',
        'proton.me',
        'zoho.com',
        'gmx.com',
        'mail.com',
        'yandex.com',
    ],

    /*
    | Privacy floor. No admin-facing aggregate may be computed from fewer
    | than this many employees (PRD §12 "minimum cohort threshold").
    */
    'aggregate_minimum_cohort' => env('BUSINESS_AGGREGATE_MIN_COHORT', 5),

    /*
    | ROI calculator coefficients (admin Overview).
    |
    | PLACEHOLDER — these are the deck's illustrative figures. They are
    | assumptions about absenteeism and productivity, not measurements, and the
    | dashboard presents the result as an estimate. Product/finance should
    | confirm them (or supply a source) before this is shown to a customer.
    */
    'roi' => [
        'absenteeism_days_per_session' => env('BUSINESS_ROI_DAYS_PER_SESSION', 0.35),
        'avg_daily_productivity_value' => env('BUSINESS_ROI_DAILY_VALUE', 21000),
    ],
];
