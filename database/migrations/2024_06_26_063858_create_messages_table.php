<?php

use App\Constants\Messaging\MessagingConstants;
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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained("conversations")->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained("users")->cascadeOnDelete();
            $table->foreignId("receiver_id")->constrained("users")->cascadeOnDelete();
            $table->longText('message')->nullable();
            $table->string('asset_url')->nullable();
            $table->string('message_type')->default(MessagingConstants::TEXT);
            $table->boolean('read')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
