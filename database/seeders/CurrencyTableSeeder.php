<?php

namespace Database\Seeders;

use App\Constants\Finance\Currency\CurrencyConstants;
use App\Constants\General\StatusConstants;
use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencyTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $currencies = [
            [
                "name" => "Nigerian Naira",
                "group" => CurrencyConstants::FIAT_GROUP,
                "type" => CurrencyConstants::NAIRA_CURRENCY,
                "short_name" => "NGN",
                "symbol" => "₦",
                "status" => StatusConstants::ACTIVE
            ],
            [
                "name" => "Us Dollar",
                "group" => CurrencyConstants::FIAT_GROUP,
                "type" => CurrencyConstants::DOLLAR_CURRENCY,
                "short_name" => "USD",
                "symbol" => "$",
                "status" => StatusConstants::ACTIVE
            ],
            [
                "name" => "Euro",
                "group" => CurrencyConstants::FIAT_GROUP,
                "type" => CurrencyConstants::EURO_CURRENCY,
                "short_name" => "EUR",
                "symbol" => "€",
                "status" => StatusConstants::ACTIVE
            ],
            [
                "name" => "Pound",
                "group" => CurrencyConstants::FIAT_GROUP,
                "type" => CurrencyConstants::POUND_CURRENCY,
                "short_name" => "GBP",
                "symbol" => "£",
                "status" => StatusConstants::ACTIVE
            ],
        ];

        foreach ($currencies as $currency) {
            Currency::firstOrCreate(["type" => $currency["type"]], $currency);
        }
    }
}
