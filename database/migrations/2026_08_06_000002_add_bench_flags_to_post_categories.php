<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets an admin curate which interest-topic categories appear on the B2B
 * "Preview your therapist bench" screen (web §4b) and in what order — without a
 * second taxonomy. The bench, therapist specialties, employee interests and the
 * mobile therapist directory all read the same `post_categories` rows, so a
 * company that prioritises "Anxiety" is pointing at the exact category the app
 * matches therapists on. Additive: both columns default off / zero, so every
 * existing category and the mobile app are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table("post_categories", function (Blueprint $table) {
            $table->boolean("is_bench_featured")->default(false)->index()->after("type");
            $table->unsignedInteger("bench_sort")->default(0)->after("is_bench_featured");
        });
    }

    public function down(): void
    {
        Schema::table("post_categories", function (Blueprint $table) {
            $table->dropColumn(["is_bench_featured", "bench_sort"]);
        });
    }
};
