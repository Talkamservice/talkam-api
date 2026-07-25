<?php

namespace Tests\Feature\V2\Business;

use App\Constants\General\StatusConstants;
use App\Models\Industry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public B2B signup options (web §01): the admin-managed industry list and
 * the headcount bands surfaced through pricing-config.
 */
class IndustryTest extends TestCase
{
    use RefreshDatabase;

    public function test_industries_returns_active_list_in_order(): void
    {
        Industry::create(["name" => "Technology", "slug" => "technology", "sort_order" => 1, "status" => StatusConstants::ACTIVE]);
        Industry::create(["name" => "Banking & Finance", "slug" => "banking-finance", "sort_order" => 0, "status" => StatusConstants::ACTIVE]);

        $response = $this->getJson("/api/v2/business/industries");

        $response->assertOk();
        $names = collect($response->json("data"))->pluck("name")->all();

        // Ordered by sort_order, so Banking (0) precedes Technology (1).
        $this->assertSame(["Banking & Finance", "Technology"], $names);
    }

    public function test_industries_excludes_inactive(): void
    {
        Industry::create(["name" => "Live", "slug" => "live", "status" => StatusConstants::ACTIVE]);
        Industry::create(["name" => "Hidden", "slug" => "hidden", "status" => StatusConstants::INACTIVE]);

        $names = collect($this->getJson("/api/v2/business/industries")->json("data"))
            ->pluck("name")
            ->all();

        $this->assertContains("Live", $names);
        $this->assertNotContains("Hidden", $names);
    }

    public function test_seeder_populates_the_industry_list(): void
    {
        $this->seed(\Database\Seeders\IndustrySeeder::class);

        $names = collect($this->getJson("/api/v2/business/industries")->json("data"))
            ->pluck("name")
            ->all();

        $this->assertContains("Banking & Finance", $names);
        $this->assertContains("Healthcare & Pharmaceuticals", $names);
        $this->assertSame("Other", end($names)); // "Other" always sorts last
    }

    public function test_pricing_config_exposes_headcount_bands_starting_at_one(): void
    {
        $bands = $this->getJson("/api/v2/business/pricing-config")->json("data.headcount_bands");

        $this->assertContains("1 – 50", $bands);
        $this->assertSame("1 – 50", $bands[0]); // the newly-added smallest band leads
    }
}
