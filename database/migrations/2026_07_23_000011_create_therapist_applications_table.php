<?php

use App\Constants\Therapist\TherapistConstants;
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
        Schema::create('therapist_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default(TherapistConstants::STATUS_DRAFT);
            $table->string('credential_type')->nullable();
            $table->smallInteger('years_experience')->nullable();
            $table->text('bio')->nullable();
            $table->smallInteger('session_duration')->nullable();
            $table->smallInteger('buffer_minutes')->nullable();
            $table->decimal('session_rate', 12, 2)->nullable();
            $table->json('session_formats')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('therapist_applications');
    }
};
