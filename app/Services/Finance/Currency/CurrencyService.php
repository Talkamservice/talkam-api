<?php

namespace App\Services\Finance\Currency;

use App\Constants\Finance\Currency\CurrencyConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\Currency;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CurrencyService
{

    const CURRENCY_NOT_FOUND = 3404;

    public static function getByType($type): Currency
    {
        $currency = Currency::where("type", $type)->first();

        if (empty($currency)) {
            $currency = Currency::firstOrCreate([
                "name" => "Nigerian Naira",
                "group" => CurrencyConstants::CURRENCY_GROUP,
                "type" => CurrencyConstants::NAIRA_CURRENCY,
                "price_per_dollar" => 700,
                "short_name" => "NGN",
            ], [    
                "logo" => "/",
                "status" => StatusConstants::ACTIVE
            ]);
        }
        return $currency;
    }

    public static function getById($key, $column = "id"): Currency
    {
        $currency = Currency::where($column, $key)->first();

        if (empty($currency)) {
            throw new ModelNotFoundException(
                "Currency not found"
            );
        }
        return $currency;
    }

    public static function validate(array $data, $id = null): array
    {
        $validator = Validator::make($data, [
            "status" => "required|string|" . Rule::in(StatusConstants::ACTIVE_OPTIONS),
            "name" => 'required|string',
            "type" => "required|string|unique:currencies,type,$id",
            "price_per_dollar" => 'required|numeric|gt:-1',
            "short_name" => "required|string|unique:currencies,short_name,$id",
            "logo" => "image|" . Rule::requiredIf(empty($id)),
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public function list()
    {
        $currencies = Currency::query();
        return $currencies;
    }

    public function updateRate($short_name, $rate)
    {
        $currency = Currency::where("short_name", $short_name)->first();

        if (empty($currency)) {
            $currency = Currency::firstOrCreate([
                "group" => CurrencyConstants::FIAT_GROUP,
                "short_name" => $short_name,
            ], [
                "name" => $short_name,
                "price_per_dollar" => $rate,
                "type" => $short_name,
                "status" => StatusConstants::ACTIVE
            ]);
        }

        $currency->update([
            "price_per_dollar" => $rate
        ]);

        return $currency;
    }
}
