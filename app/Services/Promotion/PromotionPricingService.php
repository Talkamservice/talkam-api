<?php

namespace App\Services\Promotion;
use App\Constants\General\StatusConstants;
use App\Exceptions\General\ModelNotFoundException;
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
            "currency_id" => 'required|exists:countries,id',
            "amount" => "required|numeric",
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

            $promotion_pricing = PromotionPricing::create([
                "country_id" => $data['country_id'],
                "currency_id" => $data['currency_id'],
                "amount" => $data['amount'],
                "impressions" => $data['impressions'],
                "status" => StatusConstants::ACTIVE,
            ]);
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
}
