<?php

namespace Database\Seeders;

use App\Constants\General\StatusConstants;
use App\Constants\Post\PostCategoryConstants;
use App\Models\PostCategory;
use Illuminate\Database\Seeder;

/**
 * Seeds the "Mental Health" parent category and the v2 onboarding interest
 * topics as its children, marked with type = interest_topic (see planning
 * doc 02 §3a). v1 category listings hide children by design; topics are
 * always selected by the marker, never by ID. Idempotent.
 */
class InterestTopicSeeder extends Seeder
{
    const TOPICS = [
        'Anxiety',
        'Depression',
        'Fear',
        'Grief',
        'Bipolar',
        'OCD',
        'ADHD',
        'Addiction',
        'Eating Disorder',
        'Psychosis',
        'Schizophrenia',
        'Insomnia',
        'Panic',
        'PTSD',
        'Relationships',
        'Physical Abuse',
        'Job Loss',
        'Social Isolation',
    ];

    public function run(): void
    {
        $parent = PostCategory::firstOrCreate(
            ['name' => 'Mental Health', 'category_id' => null],
            ['description' => 'Mental health topics', 'status' => StatusConstants::ACTIVE]
        );

        foreach (self::TOPICS as $topic) {
            PostCategory::firstOrCreate(
                ['name' => $topic, 'category_id' => $parent->id],
                [
                    'status' => StatusConstants::ACTIVE,
                    'type' => PostCategoryConstants::TYPE_INTEREST_TOPIC,
                ]
            );
        }
    }
}
