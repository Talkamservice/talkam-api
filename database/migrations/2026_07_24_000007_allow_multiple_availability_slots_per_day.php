<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * §06 onboarding stored one availability row per day (unique on
     * user_id + day_of_week). The web §04 Availability editor lets a therapist
     * keep several discrete slots on the same day, so the one-row-per-day
     * uniqueness has to go. Additive: onboarding's single-row writes still
     * work; the live editor can now add more.
     *
     * On MySQL the user_id FK leans on the composite index, so the FK is dropped
     * and re-added around the swap. SQLite (test DB) has no such dependency.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        Schema::table('therapist_availabilities', function (Blueprint $table) use ($driver) {
            if ($driver === 'mysql') {
                $table->dropForeign(['user_id']);
            }

            $table->dropUnique(['user_id', 'day_of_week']);
            $table->index(['user_id', 'day_of_week']);

            if ($driver === 'mysql') {
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        Schema::table('therapist_availabilities', function (Blueprint $table) use ($driver) {
            if ($driver === 'mysql') {
                $table->dropForeign(['user_id']);
            }

            $table->dropIndex(['user_id', 'day_of_week']);
            $table->unique(['user_id', 'day_of_week']);

            if ($driver === 'mysql') {
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            }
        });
    }
};
