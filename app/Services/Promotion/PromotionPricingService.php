<?php

namespace App\Services\Promotion;

use App\Constants\General\StatusConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\PostPerformance;
use App\Models\PromotionPricing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PromotionPricingService
{
    public static function getById($id): PromotionPricing
    {
        $plan = PromotionPricing::find($id);
        if (empty($plan)) {
            throw new ModelNotFoundException(" Promotion Pricing not found");
        }
        return $plan;
    }

    public static function validate(array $data, $id = null): array
    {
        $validator = Validator::make($data, [
            "country_id" => 'required|exists:countries,id',
            // "currency_id" => 'required|exists:countries,id',
            "amount" => "required|numeric",
            // "max_daily_amount" => "required|numeric",
            "default" => "nullable|string",
            "impressions" => "required|numeric",
            'status' => 'string|nullable',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public function create(array $data)
    {
        DB::beginTransaction();
        try {
            $data = self::validate($data);
            $avg_impressions_per_day = PostPerformance::whereNull('post_id')
                ->whereNull('group_id')
                ->pluck('avg_impressions_per_day')
                ->first() ?? 0;
            if ($data['impressions'] > $avg_impressions_per_day) {
                throw new InvalidRequestException("The entered impressions can not be greater that the Average performance per day. Currently you have " . $avg_impressions_per_day);
            }
            $max_daily_amount = $data['amount'] + 1000;
            $promotion_pricing = PromotionPricing::create([
                "country_id" => $data['country_id'],
                // "currency_id" => $data['currency_id'],
                "amount" => $data['amount'],
                "impressions" => $data['impressions'],
                "max_daily_amount" => $max_daily_amount,
                "default" => $data['default'] ?? 0,
                "status" => StatusConstants::ACTIVE,
            ]);

            if ($promotion_pricing?->default == 1) {
                PromotionPricing::whereNotIn("id", [$promotion_pricing?->id])
                    ->update([
                        "default" => 0
                    ]);
            }

            DB::commit();
            return $promotion_pricing->refresh();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }


    public function update(array $data, $id)
    {
        DB::beginTransaction();
        try {
            $data = self::validate($data, $id);
            $promotion_pricing = $this->getById($id);
            $promotion_pricing->update($data);
            DB::commit();
            return $promotion_pricing;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function save(array $data, $id = null)
    {
        if (!empty($id)) {
            $plan = $this->update($data, $id);
        } else {
            $plan = $this->create($data);
        }

        return $plan;
    }

    public static function list()
    {
        $promotion_pricings = PromotionPricing::status()->latest();
        return $promotion_pricings;
    }

    static function calculatePricing(array $data)
    {
        try {
            $validator = Validator::make($data, [
                "amount" => "required|numeric",
                "daily_budget" => "required|numeric",
                "duration" => "required|numeric",
                "impressions" => "required|numeric",
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $data = $validator->validated();

            $daily_payment = $data["daily_budget"];
            $total_amount = $data["daily_budget"] * $data["duration"];
            $total_impressions = $data["impressions"] * $data["duration"];

            return [
                "duration" => $data["duration"],
                "total_amount" => $total_amount,
                "daily_payment" => $daily_payment,
                "total_impressions" => $total_impressions,
            ];
        } catch (\Throwable $th) {
            throw $th;
        }
    }
}
