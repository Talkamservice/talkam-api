<?php

namespace App\Http\Controllers\Admin\Finance\Promotion;

use App\Constants\Finance\Plan\PlanConstants;
use App\Constants\General\AppConstants;
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
        $period = $request->period ?? 'month';
        $currency = request()->input('currency') ?? 'Nigerian Naira (NGN)';
        $period = $request->currency_short_name ?? 'NGN';
        $currency_symbol = $this->promotion_stat_service->getCurrencySymbol();
        $high_promotions = Promotion::whereHas('currency', function ($query) use ($currency_symbol) {
            $query->where('symbol', $currency_symbol);
        })->with('currency')->orderBy("cost", "desc")->limit(5)->get();
        $promotion_stats = $this->promotion_stat_service->stats(['period' => $period, 'currency' => $currency]);
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
            ->paginate(AppConstants::ADMIN_PAGINATION_SIZE);

        return view('dashboards.admin.pages.finance.promotions.list', [
            'promotions' => $promotions,
            "sn" => $promotions->firstItem(),
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
        $promotions = Promotion::where('status', $request->status)->latest()->search($request->search)
            ->paginate(AppConstants::ADMIN_PAGINATION_SIZE);
        $promotion_status = [];
        if ($request->status === StatusConstants::ACTIVE) {
            $promotion_status = "Completed";
        } elseif ($request->status === StatusConstants::PENDING) {
            $promotion_status = "Ongoing";
        } else {
            $promotion_status = "Inactive";
        }
        return view('dashboards.admin.pages.finance.promotions.get-by-status', [
            'promotions' => $promotions,
            'promotion_status' => $promotion_status,
            "sn" => $promotions->firstItem(),
        ]);
    }

    public function viewAnalytic(Request $request, $promotionId)
    {
        $period = $request->get('period', 'day');

        if (!in_array($period, ['day', 'week', 'month', 'year'])) {
            $period = 'month';
        }

        // Pass the promotion ID to get data for the specified promotion
        $promotionData = $this->promotion_stat_service->getSinglePromotion($promotionId, $period);
        return view('dashboards.admin.pages.finance.promotions.single', [
            'promotion_data' => $promotionData['promotion_data'],
            'data_labels' => $promotionData['data_labels'],
            'promotion' => $promotionData['uuid'],
        ]);
    }

    public function cancelPromotedContent($promotion)
    {
        try {
            $this->promotion_service->cancelPromotion($promotion);
            return back()->with(NotificationConstants::SUCCESS_MSG, "Promotion cancelled successfully");
        } catch (ValidationException $th) {
            throw $th;
        } catch (\Throwable $th) {
            throw $th;
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }
}
