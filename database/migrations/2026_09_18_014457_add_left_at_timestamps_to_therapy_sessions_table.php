<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('therapy_sessions', function (Blueprint $table) {
            // Mirrors client_joined_at/therapist_joined_at — reset to null on
            // every join (a rejoin means they're active again), stamped fresh
            // on every leave, so "joined_at set AND left_at set" means
            // "currently not in the call", not just "left once, ever".
            $table->timestamp('client_left_at')->nullable()->after('client_joined_at');
            $table->timestamp('therapist_left_at')->nullable()->after('therapist_joined_at');
        });
    }

    public function down(): void
    {
        Schema::table('therapy_sessions', function (Blueprint $table) {
            $table->dropColumn(['client_left_at', 'therapist_left_at']);
        });
    }
};
