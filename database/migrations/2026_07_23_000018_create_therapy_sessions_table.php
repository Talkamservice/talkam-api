<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Double-booking is prevented at the service layer (active-status scoped
     * uniqueness on therapist_id + starts_at isn't portable as a DB index).
     */
    public function up(): void
    {
        Schema::create('therapy_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('therapist_id')->constrained('therapists')->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->smallInteger('duration_minutes');
            $table->string('format');
            $table->string('status');
            $table->decimal('amount', 12, 2);
            $table->string('currency')->default('NGN');
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('hold_expires_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('client_joined_at')->nullable();
            $table->timestamp('therapist_joined_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('cancelled_by')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->string('channel_ref')->nullable();
            $table->timestamps();

            $table->index(['therapist_id', 'starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('therapy_sessions');
    }
};
