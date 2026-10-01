<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pool of texts the daily wellness check-in nudge picks from at random.
     * Editable from the platform admin panel; seeded so the nudge varies
     * out of the box.
     */
    public function up(): void
    {
        Schema::create('wellness_nudge_messages', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120)->default('TalkAM Wellness Check-in');
            $table->string('message', 255);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('wellness_nudge_messages')->insert(array_map(fn ($message) => [
            'title' => 'TalkAM Wellness Check-in',
            'message' => $message,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], [
            "You haven't logged your mood today. How are you feeling?",
            "Take a moment for yourself: how is your day going so far?",
            "A quick check-in can make a big difference. How are you feeling right now?",
            "How are you really doing today? Log your mood in a few taps.",
            "Pause, breathe, and check in with yourself. Tell us how today feels.",
            "Your feelings matter. Add today's mood to your check-in streak.",
        ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('wellness_nudge_messages');
    }
};
