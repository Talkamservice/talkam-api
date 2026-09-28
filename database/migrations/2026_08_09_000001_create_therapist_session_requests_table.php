<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('therapist_session_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('therapist_id')->constrained('therapists')->cascadeOnDelete();
            $table->string('format');
            $table->dateTime('preferred_at');
            $table->text('note')->nullable();
            $table->string('status')->default('pending');
            // Set once the therapist proposes a concrete slot — the real,
            // payable TherapySession this request turned into.
            $table->foreignId('session_id')->nullable()->constrained('therapy_sessions')->nullOnDelete();
            $table->dateTime('proposed_starts_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['therapist_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('therapist_session_requests');
    }
};
