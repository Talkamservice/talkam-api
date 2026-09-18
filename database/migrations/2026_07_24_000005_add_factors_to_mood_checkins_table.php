<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The web check-ins screen asks "what's affecting your mood?" and takes an
     * optional note. Both columns are nullable, so §03's mobile contract —
     * which posts only {mood} — is unchanged.
     *
     * A json column rather than a pivot: the factor vocabulary is a fixed
     * 8-value list from the deck (config('v2.checkins.factors')) and is only
     * ever read back for one aggregate.
     */
    public function up(): void
    {
        Schema::table('mood_checkins', function (Blueprint $table) {
            $table->json('factors')->nullable()->after('mood');
            $table->string('note', 280)->nullable()->after('factors');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mood_checkins', function (Blueprint $table) {
            $table->dropColumn(['factors', 'note']);
        });
    }
};
