<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform Admin "Place on Review Hold" / "Clear Review Hold" — a
 * dedicated marker rather than overloading therapists.status (which
 * already means several other things: self-deactivation, admin
 * suspension via UserService::ban(), etc.), so "was this hold placed via
 * Performance Watch" stays unambiguous.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('therapists', function (Blueprint $table) {
            $table->timestamp('review_hold_at')->nullable()->after('verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('therapists', function (Blueprint $table) {
            $table->dropColumn('review_hold_at');
        });
    }
};
