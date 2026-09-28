<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * organization_id is denormalised on purpose: the admin-facing aggregate
     * (planning-docs/web-api/03) reads this table alone, filtered by
     * organization_id, and never selects user_id — so an individual's answers
     * are never joinable to employer identity through a query the admin lane
     * can reach.
     */
    public function up(): void
    {
        Schema::create('employee_self_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Explicit length: (user_id, category) is unique and
            // (organization_id, category) is indexed.
            $table->string('category', 30);
            $table->unsignedTinyInteger('score');
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'category']);
            $table->index(['organization_id', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_self_checks');
    }
};
