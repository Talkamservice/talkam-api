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
     * The ledger is the single source of truth — balance is always
     * sum(credits) − sum(debits); no stored balance column to drift.
     */
    public function up(): void
    {
        Schema::create('therapist_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('therapist_id')->constrained('therapists')->cascadeOnDelete();
            $table->string('type');
            $table->foreignId('session_id')->nullable()->constrained('therapy_sessions')->nullOnDelete();
            $table->foreignId('payout_id')->nullable()->constrained('payouts')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('status')->default(StatusConstants::COMPLETED);
            $table->string('reference')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('therapist_wallet_transactions');
    }
};
