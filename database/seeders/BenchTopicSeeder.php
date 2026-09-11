<?php

namespace Database\Seeders;

use App\Constants\Post\PostCategoryConstants;
use App\Models\PostCategory;
use Illuminate\Database\Seeder;

/**
 * Features the default therapist-bench specialties (web §4b) on the shared
 * interest-topic taxonomy. Idempotent. Ensures the interest topics exist first,
 * then flags the curated subset that appears on the bench screen, in order.
 * Keys match the historical bench list so companies keep their selections; the
 * rows themselves are the same ones therapists tag and the mobile app matches.
 */
class BenchTopicSeeder extends Seeder
{
    /** Category name => bench sort order. "PTSD" is the app's name for "PTSD / Trauma". */
    const FEATURED = [
        "Anxiety" => 0,
        "Depression" => 1,
        "Relationships" => 2,
        "Work Stress" => 3,
        "Grief" => 4,
        "PTSD" => 5,
    ];

    public function run(): void
    {
        // The bench draws from the interest-topic categories, so make sure they
        // exist before featuring the subset (harmless if already seeded).
        $this->call(InterestTopicSeeder::class);

        foreach (self::FEATURED as $name => $sort) {
            PostCategory::where("name", $name)
                ->where("type", PostCategoryConstants::TYPE_INTEREST_TOPIC)
                ->update(["is_bench_featured" => true, "bench_sort" => $sort]);
        }
    }
}
