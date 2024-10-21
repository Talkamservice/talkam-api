<?php

namespace App\Constants\Finance\Plan;

use App\Constants\General\AppConstants;

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

    const SCOPES = [
        "content_creation_access" => [
            "name" => "content_creation_access",
            "label" => "Enable Content Creation",
            "type" => "checkbox",
            "placeholder" => null,
            "data" => AppConstants::BOOL_OPTIONS,
        ],
        "ad_free_experience" => [
            "name" => "ad_free_experience",
            "label" => "Enable Ad Free Experience", 
            "type" => "checkbox",
            "placeholder" => null,
            "data" => AppConstants::BOOL_OPTIONS,
        ],
        "character_restriction" => [
            "name" => "character_restriction",
            "label" => "Character Restriction", 
            "type" => "number",
            "placeholder" => "100, 200 (Unlimited if it is empty)",
            "data" => null,
        ],
        "anonymous_content" => [
            "name" => "anonymous_content",
            "label" => "Total Anonymous Content",
            "type" => "number",
            "placeholder" => "5, 10 (Unlimited if it is empty)",
            "data" => null,
        ],
        "total_group_creation" => [
            "name" => "total_group_creation",
            "label" => "Total Groups",
            "type" => "number",
            "placeholder" => "5, 10 (Unlimited if it is empty)",
            "data" => null,
        ],
    ];
}
