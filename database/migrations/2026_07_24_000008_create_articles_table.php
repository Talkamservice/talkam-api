<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The TalkAM Journal — editorial articles for the public web blog (web §05).
 *
 * A marketing-content table, separate from the community `posts` feed. Bodies
 * are stored as an ordered array of typed blocks (p / h2 / quote / list /
 * callout) so the web renderer maps one-to-one to the deck.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create("articles", function (Blueprint $table) {
            $table->id();
            $table->string("slug", 191)->unique();
            $table->string("category", 100)->index();
            $table->string("tone", 20)->default("blue");
            $table->string("cover", 191)->nullable();
            $table->string("title");
            $table->text("excerpt")->nullable();
            $table->string("author", 150);
            $table->string("author_initials", 8)->nullable();
            $table->string("author_role", 150)->nullable();
            $table->text("author_bio")->nullable();
            $table->string("read_time", 40)->nullable();
            $table->string("display_date", 40)->nullable();
            $table->json("body")->nullable();
            $table->unsignedInteger("sort_order")->default(0)->index();
            $table->timestamp("published_at")->nullable();
            $table->string("status", 20)->default("published")->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("articles");
    }
};
