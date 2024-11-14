<?php

use App\Constants\General\StatusConstants;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHookLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('hook_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId("user_id")->nullable()->constrained("users")->cascadeOnDelete(); // Name of the webhook provider
            $table->string("source"); // Name of the webhook provider
            $table->string("event")->nullable();
            $table->json("headers")->nullable();
            $table->json("content")->nullable();
            $table->string("url");
            $table->integer("delay")->nullable()->default(0); //seconds
            $table->integer("retries")->nullable()->default(0);
            $table->dateTime("processed_at")->nullable();
            $table->dateTime("latest_retry")->nullable();
            $table->json("response")->nullable();
            $table->string("status")->default(StatusConstants::PENDING);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('hook_logs');
    }
}
