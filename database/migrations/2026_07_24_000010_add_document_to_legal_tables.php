<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The web legal pages (web §06) render a STRUCTURED document — title, an NDPA
 * callout, a contact block and an ordered list of titled sections — which the
 * existing single `body` blob (served to mobile/v1) cannot express.
 *
 * Additive: one nullable `document` json column per legal table. Mobile, v1 and
 * the admin CRUD only ever read `body`, so they are untouched; one row now
 * carries both the mobile blob and the web structured document.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table("privacy_policies", function (Blueprint $table) {
            $table->json("document")->nullable()->after("body");
        });

        Schema::table("term_and_conditions", function (Blueprint $table) {
            $table->json("document")->nullable()->after("body");
        });
    }

    public function down(): void
    {
        Schema::table("privacy_policies", function (Blueprint $table) {
            $table->dropColumn("document");
        });

        Schema::table("term_and_conditions", function (Blueprint $table) {
            $table->dropColumn("document");
        });
    }
};
