<?php

namespace App\Http\Controllers\Api\V1\User\Promotion;

use App\Constants\General\ApiConstants;
use App\Constants\General\AppConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Promotion\PromotionPricingResource;
use App\Models\PromotionPricing;
use App\Services\Finance\Plan\PlanCountryPricingService;
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

    public function promotionPricing($promotion_pricing)
    {
        try {
            $user = auth()->user();
            if ($user && !is_null($user->country_id)) {
                $promotionPricingResource = PromotionPricing::where('id', $promotion_pricing)
                    ->where('country_id', $user->country_id)
                    ->first();
            } else {
                $promotionPricingResource = PromotionPricing::where('id', $promotion_pricing)->first();
            }
            $data = PromotionPricingResource::make($promotionPricingResource);
            return ApiHelper::validResponse("Promotions returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
