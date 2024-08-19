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
<<<<<<<< HEAD:database/migrations/2024_08_15_221341_create_group_member_reports_table.php
        Schema::create('group_member_reports', function (Blueprint $table) {
========
        Schema::create('guidelines', function (Blueprint $table) {
>>>>>>>> b3c3aac306096df41a5f769351d73014036f32dd:database/migrations/2024_07_19_144551_create_guidelines_table.php
            $table->id();
            $table->foreignId('user_id')->constrained("users")->cascadeOnDelete();
            $table->foreignId("group_member_id")->constrained("group_members")->cascadeOnDelete();
            $table->longText('reason')->nullable();
            $table->string("status")->default(StatusConstants::PENDING);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_member_reports');
    }
};
