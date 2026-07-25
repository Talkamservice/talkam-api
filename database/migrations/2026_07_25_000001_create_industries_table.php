<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Industries offered on the B2B signup form (web §01). Admin-managed so the
 * list can grow without a deploy; the signup form reads the active set from
 * GET business/industries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create("industries", function (Blueprint $table) {
            $table->id();
            $table->string("name", 120)->unique();
            $table->string("slug", 140)->unique();
            $table->unsignedInteger("sort_order")->default(0)->index();
            $table->string("status", 20)->default("active")->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("industries");
    }
};
