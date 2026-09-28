<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Booking-ready: the calendar/booking sections read this schema.
     */
    public function up(): void
    {
        Schema::create('therapist_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('day_of_week');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'day_of_week']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('therapist_availabilities');
    }
};
