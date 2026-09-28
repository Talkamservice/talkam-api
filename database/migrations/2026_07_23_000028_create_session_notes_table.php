<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Private to the therapist by default; per-note share toggle.
     * status (draft|final) + tags are §12 amendments folded in.
     */
    public function up(): void
    {
        Schema::create('session_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->unique()->constrained('therapy_sessions')->cascadeOnDelete();
            $table->foreignId('therapist_id')->constrained('therapists')->cascadeOnDelete();
            $table->string('title');
            $table->longText('content')->nullable();
            $table->boolean('shared_with_client')->default(false);
            $table->string('status')->default('final');
            $table->json('tags')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_notes');
    }
};
