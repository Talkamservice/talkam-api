<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * (Runs before the wallet-transactions table, which references it.)
     */
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('therapist_id')->constrained('therapists')->cascadeOnDelete();
            $table->foreignId('payout_account_id')->constrained('therapist_payout_accounts')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('provider')->default('flutterwave');
            $table->string('provider_ref')->nullable();
            $table->string('status')->default('pending');
            $table->string('initiated_by')->default('manual');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
