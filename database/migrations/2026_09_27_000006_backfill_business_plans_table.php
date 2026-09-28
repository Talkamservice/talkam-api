<?php

use App\Models\BusinessPlan;
use Illuminate\Database\Migrations\Migration;

/**
 * One-time backfill: turns config('business.plans') — the 3 hardcoded plan
 * definitions — into real rows, so nothing changes for anyone the moment
 * this ships; only a platform admin editing them afterward changes anything.
 */
return new class extends Migration
{
    public function up(): void
    {
        $plans = config('business.plans');

        if (empty($plans)) {
            return;
        }

        $order = 0;
        foreach ($plans as $key => $plan) {
            $order++;

            $row = BusinessPlan::create([
                'key' => $plan['key'] ?? $key,
                'name' => $plan['name'],
                'seat_range' => $plan['seat_range'],
                'min_seats' => $plan['min_seats'],
                'max_seats' => $plan['max_seats'] ?? null,
                'default_seats' => $plan['default_seats'],
                'is_custom' => $plan['custom'] ?? false,
                'sort_order' => $order,
            ]);

            $tier_order = 0;
            foreach ($plan['tiers'] ?? [] as $tier) {
                $tier_order++;
                $row->tiers()->create([
                    'min_seats' => $tier['min'],
                    'max_seats' => $tier['max'] ?? null,
                    'price' => $tier['price'],
                    'sort_order' => $tier_order,
                ]);
            }

            $feature_order = 0;
            foreach ($plan['features'] ?? [] as $label) {
                $feature_order++;
                $row->features()->create([
                    'label' => $label,
                    'sort_order' => $feature_order,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Data-only backfill — nothing structural to reverse. Rows here are
        // indistinguishable from real admin edits made since, so a blanket
        // delete would risk destroying genuine changes.
    }
};
