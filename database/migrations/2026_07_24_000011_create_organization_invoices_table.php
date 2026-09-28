<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Company invoice history for the admin Billing screen (web §07).
 *
 * Read-only from the web — a real billing job owns writes; this table just
 * records what was billed per period so the admin can see their history. A
 * fresh org has none, and the live "current plan" card renders from the org's
 * plan regardless.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create("organization_invoices", function (Blueprint $table) {
            $table->id();
            $table->foreignId("organization_id")->constrained()->cascadeOnDelete();
            $table->string("reference", 40)->unique();
            $table->date("period_start");
            $table->date("period_end");
            $table->unsignedInteger("seats")->default(0);
            $table->decimal("amount", 12, 2)->default(0);
            $table->string("status", 30)->default("due")->index();
            $table->timestamp("issued_at")->nullable();
            $table->timestamp("due_at")->nullable();
            $table->timestamps();

            $table->index(["organization_id", "period_start"]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("organization_invoices");
    }
};
