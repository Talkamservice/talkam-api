<?php

use App\Constants\General\StatusConstants;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Materialized on application approval — the model
     * TherapistQueryBuilder has expected all along.
     */
    public function up(): void
    {
        Schema::create('therapists', function (Blueprint $table) {
            $table->id();
            // nullOnDelete: user deletion must not cascade away the
            // therapist row (reviews/session history hang off it — §14).
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('credential_type')->nullable();
            $table->decimal('session_rate', 12, 2)->nullable();
            $table->json('session_formats')->nullable();
            $table->smallInteger('session_duration')->nullable();
            $table->smallInteger('buffer_minutes')->nullable();
            $table->smallInteger('years_experience')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('status')->default(StatusConstants::ACTIVE);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('therapists');
    }
};
