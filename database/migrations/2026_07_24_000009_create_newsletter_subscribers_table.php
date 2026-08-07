<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Newsletter capture for the Journal's "Wellbeing, in your inbox" band
 * (web §05). Public, idempotent on email — a repeat subscribe is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create("newsletter_subscribers", function (Blueprint $table) {
            $table->id();
            $table->string("email", 191)->unique();
            $table->string("source", 60)->default("journal");
            $table->string("status", 20)->default("subscribed");
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("newsletter_subscribers");
    }
};
