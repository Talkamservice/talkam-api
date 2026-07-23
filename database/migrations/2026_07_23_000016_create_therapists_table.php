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
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('credential_type')->nullable();
            $table->decimal('session_rate', 12, 2)->nullable();
            $table->json('session_formats')->nullable();
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
