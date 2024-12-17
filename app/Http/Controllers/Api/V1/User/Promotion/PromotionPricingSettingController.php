<?php

namespace App\Http\Controllers\Api\V1\User\Promotion;

use App\Constants\General\ApiConstants;
use App\Constants\General\StatusConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Promotion\PromotionPricingResource;
use App\Models\PromotionPricing;
use App\Services\Promotion\PromotionPricingService;
use Exception;
use Illuminate\Http\Request;

class PromotionPricingSettingController extends Controller
{
    protected $promotion_pricing_service;

    public function __construct()
    {
        $this->promotion_pricing_service = new PromotionPricingService;
    }

    public function promotionPricing(Request $request)
    {
        try {
            $user = auth()->user();
            $should_calc = false;
            $promotion_pricing = !is_null($user->pricing_country_id) ? PromotionPricing::where('country_id', $user?->pricing_country_id)
                ->where("status", StatusConstants::ACTIVE)
                ->first() : null;

            if (empty($promotion_pricing)) {
                $promotion_pricing = PromotionPricing::where('default', 1)->latest()->first();
                $should_calc = true;
            }

            if (empty($promotion_pricing)) {
                $promotion_pricing = PromotionPricing::orderBy("id", "desc")->first();
                $should_calc = true;
            }

            $data = !empty($promotion_pricing) ? PromotionPricingResource::make($promotion_pricing, $should_calc) : null;
            return ApiHelper::validResponse("Promotions returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function calculatePricing(Request $request)
    {
        try {
            $data = (new PromotionPricingService)->calculatePricing($request->all());
            return ApiHelper::validResponse("Promotion pricing calculation successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
