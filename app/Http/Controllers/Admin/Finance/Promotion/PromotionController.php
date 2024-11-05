<?php

namespace App\Http\Controllers\Admin\Finance\Promotion;

use App\Constants\Finance\Plan\PlanConstants;
use App\Constants\General\NotificationConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\Payment\PlanException as PaymentPlanException;
use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Services\Finance\Plan\PlanBenefitService;
use App\Services\Finance\Plan\PlanScopeService;
use App\Services\Finance\Plan\PlanService;
use App\Services\Promotion\PromotionService;
use App\Services\Promotion\PromotionStatsService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class PromotionController extends Controller
{
    protected $promotion_service;
    protected $promotion_stat_service;

    public function __construct()
    {
        $this->promotion_service = new PromotionService;
        $this->promotion_stat_service = new PromotionStatsService;
    }

    public function index(Request $request)
    {
        $promotions = Promotion::query()->get();
        $promotion_stats = $this->promotion_stat_service->stats();
        return view('dashboards.admin.pages.finance.promotions.index', [
            'promotions' => $promotions,
            'promotion_stats' => $promotion_stats,
            "dashboardData" => $promotion_stats["dashboard_data"],
            "cards" => $promotion_stats["cards"],
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS,
        ]);
    }

    public function show($id)
    {
        $plan = PlanService::getById($id);
        return redirect()->route("admin.promotions.plan-benefits.index", $plan->id);
    }

    public function edit($id)
    {
        $plan = PlanService::getById($id);
        $benefits = PlanBenefitService::getByPlanId($plan->id);
        $plan_scopes = PlanScopeService::getByPlanId($plan->id);
        return view('dashboards.admin.pages.finance.plan.create', [
            "plan" => $plan,
            "benefits" => $benefits,
            "plan_scopes" => $plan_scopes,
            "frequencyOptions" => PlanConstants::FREQUENCY_OPTIONS,
            "featuresOptions" => PlanConstants::PLAN_FEATURES,
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS,
            "scopeOptions" => PlanConstants::SCOPES
        ]);
    }

}
