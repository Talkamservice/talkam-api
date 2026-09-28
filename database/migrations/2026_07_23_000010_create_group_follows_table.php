<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * v2 "Follow Group" (overflow menu) — a lighter follow with no
     * membership. v1 has no such record (its "following" is membership),
     * so this table is additive and v1 never touches it.
     */
    public function up(): void
    {
        Schema::create('group_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'group_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_follows');
    }
};
