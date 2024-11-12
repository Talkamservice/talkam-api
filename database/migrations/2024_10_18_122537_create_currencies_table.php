<?php

use App\Constants\Finance\Currency\CurrencyConstants;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCurrenciesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('currencies')) {
            Schema::create('currencies', function (Blueprint $table) {
                $table->id();
                $table->string("name");
                $table->enum("group" , [
                    CurrencyConstants::FIAT_GROUP,
                    CurrencyConstants::TOKEN_GROUP,
                ]);
                $table->string("type", 50)->unique();
                $table->string("short_name", 20)->unique();
                $table->string("symbol");
                $table->string("status");
                $table->timestamps();
                $table->softDeletes();
            });
        }
       
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Schema::dropIfExists('currencies');
    }
}
