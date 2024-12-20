<?php

namespace App\Services\Promotion;

use App\Constants\Finance\Currency\CurrencyConstants;
use App\Constants\General\StatusConstants;
use App\Models\Payment;
use App\Models\Promotion;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PromotionStatsService
{
    public $promotion_service;
    public $single_promotion_service;

    public function __construct()
    {
        $this->promotion_service = new PromotionService;
        // $this->single_promotion_service = new SinglePromotionService;
    }

    public function stats(array $data = [])
    {
        $period = $data["period"] ?? 'month';
        $currency = $data['currency'] ?? 'All';
        // dd($currency);
        if (!in_array($period, ['day', 'week', 'month', 'year'])) {
            $period = 'month';
        }
        if ($period === 'month') {
            $this->fetchrevenueData();
        }
        if (!in_array($currency, CurrencyConstants::CURRENCY_OPTIONS)) {
            $currency =  request()->input('currency') ?? 'Nigerian Naira (NGN)';
        }

        $promotion_data = $this->getPromotionData($period, $currency);
        $currency_symbol = $this->getCurrencySymbol();

        $data = [
            "cards" => [
                [
                    "icon" => "receipt",
                    "title" => "Total Post Ads",
                    "value" => array_sum($promotion_data['currentPostAds']),
                    "class" => "primary",
                    "percentage" => $promotion_data['postAdsChangePercentage'],
                    'period' =>  $period,
                    'currency_symbol' => $currency_symbol,
                ],
                [
                    "icon" => "home",
                    "title" => "Total Group Ads",
                    "value" => array_sum($promotion_data['currentGroupAds']),
                    "class" => "primary",
                    "percentage" => $promotion_data['groupAdsChangePercentage'],
                    'period' =>  $period,
                    'currency_symbol' => $currency_symbol,
                ],
                [
                    "icon" => "receipt",
                    "title" => "Total Post Ad Revenue",
                    "value" => format_stat_money(array_sum($promotion_data['currentPostAdRevenue']), 2, $currency_symbol),
                    "class" => "primary",
                    "percentage" => $promotion_data['postAdsRevenueChangePercentage'],
                    'period' =>  $period,
                    'currency_symbol' => $currency_symbol,
                ],
                [
                    "icon" => "home",
                    "title" => "Total Group Ad Revenue",
                    "value" => format_stat_money(array_sum($promotion_data['currentGroupAdRevenue']), 2, $currency_symbol),
                    "class" => "primary",
                    "percentage" => $promotion_data['groupAdsRevenueChangePercentage'],
                    'period' =>  $period,
                    'currency_symbol' => $currency_symbol,
                ],
                [
                    "icon" => "users",
                    "title" => "Total Freemium User",
                    "value" => array_sum($promotion_data['currentFreemiumUser']),
                    "class" => "primary",
                    "percentage" => $promotion_data['freemiumUsersChangePercentage'],
                    'period' =>  $period,
                    'currency_symbol' => $currency_symbol,
                ],
                [
                    "icon" => "users",
                    "title" => "Total Premium User",
                    "value" => array_sum($promotion_data['currentPremiumUser']),
                    "class" => "primary",
                    "percentage" => $promotion_data['premiumUsersChangePercentage'],
                    'period' =>  $period,
                    'currency_symbol' => $currency_symbol,
                ]
            ],

            "status_card" => [
                "value" => array_sum($promotion_data['totalPromotions']),
                "percentage" => $promotion_data['freemiumUsersChangePercentage'],
                "cards" => [
                    [
                        "title" => "Completed Promotions",
                        "value" => array_sum($promotion_data['totalSuccessfulPromotions']),
                        "class" => "primary",
                        "status" => StatusConstants::COMPLETED,
                    ],
                    [
                        "title" => "Ongoing Promotions",
                        "value" => array_sum($promotion_data['totalPendingPromotions']),
                        "class" => "info",
                        "status" => StatusConstants::PENDING,
                    ],
                    [
                        "title" => "Pending Promotions",
                        "value" => array_sum($promotion_data['totalInactivePromotions']),
                        "class" => "warning",
                        "status" => StatusConstants::INACTIVE,
                    ]
                ],
            ],
            "dashboard_data" => $promotion_data,
        ];
        return $data;
    }

    public function getPromotionData($period = 'month',)
    {
        switch ($period) {
            case 'day':
                $currentStartDate = Carbon::today();
                $previousStartDate = Carbon::yesterday();
                $interval = 'hour';
                $dataPoints =  24; // 24 hours in a day
                break;
            case 'week':
                $currentStartDate = Carbon::now()->startOfWeek();
                $previousStartDate = Carbon::now()->subWeek()->startOfWeek();
                $interval = 'day';
                $dataPoints = 7; // 7 days in a week
                break;
            case 'month':
                $currentStartDate = Carbon::now()->startOfMonth();
                $previousStartDate = Carbon::now()->subMonth()->startOfMonth();
                $interval = 'day';
                $dataPoints = Carbon::now()->daysInMonth; // Days in the current month
                break;
            case 'year':
                $currentStartDate = Carbon::now()->startOfYear();
                $previousStartDate = Carbon::now()->subYear()->startOfYear();
                $interval = 'month';
                $dataPoints = 12; // 12 months in a year
                break;
            default:
                $currentStartDate = Carbon::now()->startOfMonth();
                $previousStartDate = Carbon::now()->subMonth()->startOfMonth();
                $interval = 'day';
                $dataPoints = Carbon::now()->daysInMonth;
                break;
        }

        // Debug data points value

        // Fetch the current and previous period data
        $currentData = $this->fetchData($currentStartDate, $interval, $dataPoints);
        $previousData = $this->fetchData($previousStartDate, $interval, $dataPoints);

        // Calculate percentage changes
        $totalPromotionPercentage = $this->calculatePercentageChange($currentData['total_promotions'], $previousData['total_promotions']);
        $postAdsChangePercentage = $this->calculatePercentageChange($currentData['total_post_ads'], $previousData['total_post_ads']);
        $groupAdsChangePercentage = $this->calculatePercentageChange($currentData['total_group_ads'], $previousData['total_group_ads']);
        $postAdsRevenueChangePercentage = $this->calculatePercentageChange($currentData['total_post_ads_revenue'], $previousData['total_post_ads_revenue']);
        $groupAdsRevenueChangePercentage = $this->calculatePercentageChange($currentData['total_group_ads_revenue'], $previousData['total_group_ads_revenue']);
        $freemiumUsersChangePercentage = $this->calculatePercentageChange($currentData['total_freemium_users'], $previousData['total_freemium_users']);
        $premiumUsersChangePercentage = $this->calculatePercentageChange($currentData['total_premium_users'], $previousData['total_premium_users']);
        return [
            'totalPromotions' => $currentData['total_promotions'],
            'currentPostAds' => $currentData['total_post_ads'],
            'currentGroupAds' => $currentData['total_group_ads'],
            'currentPostAdRevenue' => $currentData['total_post_ads_revenue'],
            'currentGroupAdRevenue' => $currentData['total_group_ads_revenue'],
            'currentFreemiumUser' => $currentData['total_freemium_users'],
            'currentPremiumUser' => $currentData['total_premium_users'],
            'totalSuccessfulPromotions' => $currentData['total_successful_promotions'],
            'totalPendingPromotions' => $currentData['total_pending_promotions'],
            'totalInactivePromotions' => $currentData['total_inactive_promotions'],
            'totalPromotionPercentage' => $totalPromotionPercentage,
            'postAdsChangePercentage' => $postAdsChangePercentage,
            'groupAdsChangePercentage' => $groupAdsChangePercentage,
            'postAdsRevenueChangePercentage' => $postAdsRevenueChangePercentage,
            'groupAdsRevenueChangePercentage' => $groupAdsRevenueChangePercentage,
            'freemiumUsersChangePercentage' => $freemiumUsersChangePercentage,
            'premiumUsersChangePercentage' => $premiumUsersChangePercentage,

        ];
    }

    public function fetchrevenueData()
    {
        $monthlyRevenue = array_fill(0, 12, 0);
        for ($month = 0; $month < 12; $month++) {
            $monthStart = Carbon::now()->startOfYear()->addMonths($month)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();
            $currency_symbol = $this->getCurrencySymbol();
            $monthlyRevenue[$month] = Promotion::whereHas("payment")->whereBetween('created_at', [$monthStart, $monthEnd])->whereHas('currency', function ($query) use ( $currency_symbol) {
                $query->where('symbol',  $currency_symbol);
            })->with('currency')->sum('cost');
        }
        return [
            'revenue' => $monthlyRevenue,
            'currency_symbol' => $currency_symbol
        ];
    }
    private function fetchData($startDate, $interval, $dataPoints)
    {
         $currency_symbol = $this->getCurrencySymbol();
        $promotions = Promotion::whereHas("payment")->whereHas('currency', function ($query) use ( $currency_symbol) {
            $query->where('symbol',  $currency_symbol);
        })->with('currency');

        $total_post_ads = array_fill(0, $dataPoints, 0);
        $total_group_ads = array_fill(0, $dataPoints, 0);
        $total_post_ads_revenue = array_fill(0, $dataPoints, 0);
        $total_group_ads_revenue = array_fill(0, $dataPoints, 0);
        $total_freemium_users = array_fill(0, $dataPoints, 0);
        $total_premium_users = array_fill(0, $dataPoints, 0);
        $total_promotions = array_fill(0, $dataPoints, 0);
        $total_successful_promotions = array_fill(0, $dataPoints, 0);
        $total_pending_promotions = array_fill(0, $dataPoints, 0);
        $total_inactive_promotions = array_fill(0, $dataPoints, 0);

        for ($i = 0; $i < $dataPoints; $i++) {
            $startOfInterval = $startDate->copy()->add($i, $interval);
            $endOfInterval = $startOfInterval->copy()->endOf($interval);
            $total_post_ads[$i] = $promotions->clone()->whereNotNull("post_id")
                ->whereBetween('created_at', [$startOfInterval, $endOfInterval])
                ->count();

            $total_successful_promotions[$i] = $promotions->clone()
                ->whereBetween('created_at', [$startOfInterval, $endOfInterval])
                ->status()
                ->count();

            $total_pending_promotions[$i] = $promotions->clone()
                ->whereBetween('created_at', [$startOfInterval, $endOfInterval])
                ->status(StatusConstants::PENDING)
                ->count();

            $total_inactive_promotions[$i] = $promotions->clone()
                ->whereBetween('created_at', [$startOfInterval, $endOfInterval])
                ->status(StatusConstants::INACTIVE)
                ->count();

            $total_promotions[$i] = $promotions->clone()
                ->whereBetween('created_at', [$startOfInterval, $endOfInterval])
                ->count();

            $total_group_ads[$i] = $promotions->clone()->whereNotNull("group_id")
                ->whereBetween('created_at', [$startOfInterval, $endOfInterval])
                ->count();

            $total_post_ads_revenue[$i] = $promotions->clone()->whereNotNull("post_id")
                ->whereBetween('created_at', [$startOfInterval, $endOfInterval])
                ->sum("cost");

            $total_group_ads_revenue[$i] = $promotions->clone()->whereNotNull("group_id")
                ->whereBetween('created_at', [$startOfInterval, $endOfInterval])
                ->sum("cost");

            $total_premium_users[$i] = User::where('status', StatusConstants::ACTIVE)
                ->whereHas('activeSubscription', function ($query) use ($startOfInterval, $endOfInterval) {
                    $query->whereBetween('created_at', [$startOfInterval, $endOfInterval]);
                })->count();


            $total_freemium_users[$i] = User::where('status', StatusConstants::ACTIVE)
                ->whereBetween('created_at', [$startOfInterval, $endOfInterval]) // Filter users created in a specific period
                ->whereDoesntHave('activeSubscription', function ($query) use ($startOfInterval, $endOfInterval) {
                    $query->whereBetween('created_at', [$startOfInterval, $endOfInterval]);
                })
                ->count();
        }

        return [
            'total_successful_promotions' => $total_successful_promotions,
            'total_pending_promotions' => $total_pending_promotions,
            'total_inactive_promotions' => $total_inactive_promotions,
            'total_promotions' => $total_promotions,
            'total_post_ads' => $total_post_ads,
            'total_group_ads' => $total_group_ads,
            'total_post_ads_revenue' => $total_post_ads_revenue,
            'total_group_ads_revenue' => $total_group_ads_revenue,
            'total_freemium_users' => $total_freemium_users,
            'total_premium_users' => $total_premium_users,
        ];
    }


    private function calculatePercentageChange($currentData, $previousData)
    {
        $currentTotal = array_sum($currentData);
        $previousTotal = array_sum($previousData);

        if ($previousTotal == 0) {
            return $currentTotal > 0 ? 100 : 0;
        }

        return round((($currentTotal - $previousTotal) / $previousTotal) * 100, 2);
    }

    public function getSinglePromotion($promotionId, $period)
    {
        $promotion = (new SinglePromotionService())->getPromotionData($promotionId, $period);
        return $promotion;
    }

    public function getCurrencySymbol()
    {
        $currencyName = request()->input('currency') ?? 'Nigerian Naira (NGN)';
        // dd($currencyName);
         $currency_symbol = CurrencyConstants::CURRENCY_NAME_TO_SYMBOL[$currencyName] ?? null;
        if ( $currency_symbol) {
            return  $currency_symbol;
        }
        if (! $currency_symbol) {
            return redirect()->back()->withErrors(['currency' => 'Not sure you selected the right currency.']);
        }
    }
}
