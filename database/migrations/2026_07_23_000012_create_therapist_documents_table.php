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
        Schema::create('therapist_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('therapist_applications')->cascadeOnDelete();
            $table->string('type');
            $table->foreignId('file_id')->constrained('files')->cascadeOnDelete();
            $table->string('status')->default(TherapistConstants::DOC_STATUS_PENDING);
            $table->text('rejection_reason')->nullable();
            $table->date('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['application_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('therapist_documents');
    }
};
