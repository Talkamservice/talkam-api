<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The B2B plan catalogue (Wellbeing Lite/Core/Plus) — was a hardcoded array
 * in config/business.php ('plans' key), now a real, platform-admin-editable
 * table. This is the DISPLAY catalogue only (the plan cards + their own
 * volume-tier tables + feature lists, exactly what OrganizationBillingService
 * ::catalogue() already exposed to the business-admin billing page) — the
 * separate flat config('business.seat_tiers') array that
 * OrganizationPricingService::tier()/quote() actually bills against is
 * deliberately untouched by this migration (a real, higher-stakes change to
 * live invoice math, not what was asked for here).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_plans', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('seat_range');
            $table->unsignedInteger('min_seats');
            $table->unsignedInteger('max_seats')->nullable();
            $table->unsignedInteger('default_seats');
            $table->boolean('is_custom')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('business_plan_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('min_seats');
            $table->unsignedInteger('max_seats')->nullable();
            $table->unsignedInteger('price');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('business_plan_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_plan_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_plan_features');
        Schema::dropIfExists('business_plan_tiers');
        Schema::dropIfExists('business_plans');
    }
};
