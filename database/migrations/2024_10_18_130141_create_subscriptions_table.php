<?php

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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Foreign key to users table
            $table->foreignId('plan_id')->constrained()->onDelete('cascade'); // Foreign key to plans table
            $table->foreignId('plan_duration_id')->constrained()->onDelete('cascade'); // Foreign key to plan_durations table
            $table->decimal('price', 10, 2); // Subscription price
            $table->string('status'); // Active, Inactive Cancelled, etc.
            $table->string('flutterwave_subscription_id')->nullable(); // For Flutterwave subscription ID
            $table->timestamp('paid_on')->nullable(); // Timestamp when the payment was made
            $table->timestamp('expires_at')->nullable(); // Timestamp for when the subscription expires
            $table->string('currency')->default('NGN');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
