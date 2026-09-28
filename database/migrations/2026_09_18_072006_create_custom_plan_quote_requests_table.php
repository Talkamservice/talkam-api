<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_plan_quote_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            // A unique index enforces "one request per organization, ever" at
            // the data layer, not just in the UI.
            $table->unique('organization_id');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('team_size')->nullable();
            $table->string('email', 191);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending'); // pending | contacted
            $table->timestamp('contacted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_plan_quote_requests');
    }
};
