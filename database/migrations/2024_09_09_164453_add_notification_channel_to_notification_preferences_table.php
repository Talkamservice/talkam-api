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
        Schema::table('notification_preferences', function (Blueprint $table) {
            if (!Schema::hasColumns("notification_preferences", ["can_receive_sms"])) {
                $table->tinyInteger("can_receive_sms")->nullable()->default(1)->after("comments");
                $table->tinyInteger("can_receive_mail")->nullable()->default(1)->after("can_receive_sms");
                $table->tinyInteger("can_receive_push")->nullable()->default(1)->after("can_receive_mail");
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            if (Schema::hasColumns("notification_preferences", ["can_receive_sms"])) {
                $table->dropColumn("can_receive_sms");
                $table->dropColumn("can_receive_mail");
                $table->dropColumn("can_receive_push");
            }
        });
    }
};
