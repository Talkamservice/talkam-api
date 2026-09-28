<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The deck's past-sessions list shows a pre → post mood pair per session
     * ("😔 → 🙂"), captured by the pre-session modal and the feedback modal.
     * Client-owned data: the therapist's own notes stay in session_notes.
     */
    public function up(): void
    {
        Schema::table('therapy_sessions', function (Blueprint $table) {
            $table->unsignedTinyInteger('client_pre_mood')->nullable()->after('client_joined_at');
            $table->unsignedTinyInteger('client_post_mood')->nullable()->after('client_pre_mood');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('therapy_sessions', function (Blueprint $table) {
            $table->dropColumn(['client_pre_mood', 'client_post_mood']);
        });
    }
};
