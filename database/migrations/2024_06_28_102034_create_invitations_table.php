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
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId("user_id")->nullable()->constrained("users")->cascadeOnDelete();
            $table->foreignId("invited_by")->constrained("users")->cascadeOnDelete();
            $table->string('uuid' , 100)->unique();
            $table->foreignId('role_id')->nullable();
            $table->string('invitee_email')->nullable();
            $table->dateTime('invite_expires_at')->nullable();
            $table->dateTime('response_date')->nullable();
            $table->string('response')->nullable();
            $table->string('source')->nullable();
            $table->dateTime('revoke_at')->nullable();
            $table->tinyInteger('notify_on_join')->default(0);
            $table->string('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
