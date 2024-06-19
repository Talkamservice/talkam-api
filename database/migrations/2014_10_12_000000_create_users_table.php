<?php

use App\Constants\General\StatusConstants;
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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('role');
            $table->string('avatar')->nullable();
            $table->string('email')->unique();
            $table->string('phone_number')->nullable();
            $table->string('username')->unique()->nullable();
            $table->string('age')->nullable();
            $table->string('gender')->nullable();
            $table->string('registration_platform')->nullable();
            $table->string('social_id')->nullable();
            $table->string('fcm_token', 500)->nullable();
            $table->string('password')->nullable();
            $table->string('status')->default(StatusConstants::ACTIVE);
            $table->rememberToken();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
