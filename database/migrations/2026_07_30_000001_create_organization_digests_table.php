<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per org per period — the idempotency guard for the monthly
     * usage-digest email (web §03 Settings → "Monthly usage digest"), mirroring
     * how organization_invoices guards the billing run.
     */
    public function up(): void
    {
        Schema::create('organization_digests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'period_start']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_digests');
    }
};
