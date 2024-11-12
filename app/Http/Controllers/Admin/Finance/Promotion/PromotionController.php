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
        $high_promotions = Promotion::orderBy("cost", "desc")->limit(5)->get();
        $promotion_stats = $this->promotion_stat_service->stats();
        $revenue_data = $this->promotion_stat_service->fetchrevenueData();
        return view('dashboards.admin.pages.finance.promotions.index', [
            'promotion_stats' => $promotion_stats,
            'high_promotions' => $high_promotions,
            "dashboardData" => $promotion_stats["dashboard_data"],
            "revenue_data" => $revenue_data,
            "cards" => $promotion_stats["cards"],
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS,
        ]);
    }

    public function items(Request $request)
    {
        $promotions = Promotion::latest()
            ->search($request->search)
            ->filterByType($request->type)  // Add filter for type
            ->filterByStatus($request->status)  // Add filter for status
            ->with('user')
            ->get();

        return view('dashboards.admin.pages.finance.promotions.list', [
            'promotions' => $promotions,
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

    public function getByStatus(Request $request)
    {
        $promotions = Promotion::where('status', $request->status)->latest()->search($request->search)->get();
        $promotion_status = [];
        if ($request->status === StatusConstants::ACTIVE) {
            $promotion_status = "Completed";
        } elseif ($request->status === StatusConstants::PENDING) {
            $promotion_status = "Ongoing";
        } else {
            $promotion_status = "Pending";
        }
        return view('dashboards.admin.pages.finance.promotions.get-by-status', [
            'promotions' => $promotions,
            'promotion_status' => $promotion_status,
        ]);
    }
}
