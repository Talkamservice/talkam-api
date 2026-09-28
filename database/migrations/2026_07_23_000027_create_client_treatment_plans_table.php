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
        Schema::create('client_treatment_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('therapist_id')->constrained('therapists')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('total_sessions');
            $table->string('progress_status');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['therapist_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_treatment_plans');
    }
};
