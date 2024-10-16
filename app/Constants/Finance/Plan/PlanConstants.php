<?php

namespace App\Constants\Finance\Plan;

class PlanConstants
{
    const DAILY = "Daily";
    const WEEKLY = "Weekly";
    const MONTHLY = "Monthly";
    const YEARLY = "Yearly";

    const FREQUENCY_OPTIONS = [
        self::MONTHLY => "Monthly",
        self::YEARLY => "Yearly",
    ];

    const PLAN_FEATURES = [
        "content_creation_access" => "Access to most community features, including posting, commenting, and voting",
        "unrestricted_character" => "Unlimited character when posting",
        "ad_free_experience" => "Enjoy an ad-free experience",
        "enhanced_privacy" => "Advanced privacy controls, including anonymous browsing within the posts and comments",
        "unlimited_number_groups" => "Access to create an unlimited number of groups"
    ];

    const FEATURE_CARDS = [
        "content_creation_access" => "Access to most community features, including posting, commenting, and voting",
        "unrestricted_character" => "Unlimited character when posting",
        "ad_free_experience" => "Enjoy an ad-free experience",
        "enhanced_privacy" => "Advanced privacy controls, including anonymous browsing within the posts and comments",
        "unlimited_number_groups" => "Access to create an unlimited number of groups"
    ];
}
