<?php

namespace App\Constants\Post;

class PostCategoryConstants
{
    /**
     * Marker for post_categories rows that are v2 onboarding interest topics.
     * Rows are children of the "Mental Health" parent category and are always
     * selected by this marker — never by ID (IDs differ per environment).
     */
    const TYPE_INTEREST_TOPIC = 'interest_topic';
}
