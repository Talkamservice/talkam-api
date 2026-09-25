<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Real assignment/escalation workflow for the platform-admin Disputes
 * page — `amount` maps a dispute to a concrete NGN figure when one
 * applies (a session's charge, a billing double-charge, etc.), rather
 * than always requiring the reader to dig into the linked subject.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->decimal('amount', 12, 2)->nullable()->after('description');
            $table->foreignId('assigned_to')->nullable()->after('reporter_id')->constrained('users')->nullOnDelete();
            $table->timestamp('escalated_at')->nullable()->after('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn(['amount', 'escalated_at']);
        });
    }
};
