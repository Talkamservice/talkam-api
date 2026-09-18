<?php

namespace Database\Seeders;

use App\Constants\General\StatusConstants;
use App\Models\Industry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the B2B signup industries (web §01). Idempotent on name; the admin owns
 * the list from here (add / rename / deactivate). "Other" is kept last.
 */
class IndustrySeeder extends Seeder
{
    const INDUSTRIES = [
        "Banking & Finance",
        "Technology",
        "Professional Services",
        "Manufacturing",
        "Healthcare & Pharmaceuticals",
        "Insurance",
        "Telecommunications",
        "Oil & Gas / Energy",
        "Retail & E-commerce",
        "Education",
        "Construction & Real Estate",
        "Transportation & Logistics",
        "Hospitality & Tourism",
        "Media & Entertainment",
        "Agriculture & Agribusiness",
        "Consulting",
        "Legal Services",
        "Government & Public Sector",
        "Non-profit & NGO",
        "Other",
    ];

    public function run(): void
    {
        foreach (self::INDUSTRIES as $index => $name) {
            Industry::updateOrCreate(
                ["name" => $name],
                [
                    "slug" => Str::slug($name),
                    // "Other" sorts to the very end regardless of insert order.
                    "sort_order" => $name === "Other" ? 999 : $index,
                    "status" => StatusConstants::ACTIVE,
                ]
            );
        }
    }
}
