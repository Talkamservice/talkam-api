<?php

namespace App\Services\Promotion;

use App\Models\Promotion;
use App\Models\User;
use Carbon\Carbon;

class PromotionStatsService
{
    public $promotion_service;

    public function __construct()
    {
        $this->promotion_service = new PromotionService;
    }

    public function stats(array $data = [])
    {
        $period = $data["period"] ?? null;

        if (!in_array($period, ['day', 'week', 'month', 'year'])) {
            $period = 'month';
        }

        $promotion_data = $this->getPromotionData($period);

        $data = [
            "cards" => [
                [
                    "icon" => "receipt",
                    "title" => "Total Post Ads",
                    "value" => array_sum($promotion_data['currentPostAds']),
                    "class" => "primary",
                    "percentage" => $promotion_data['postAdsChangePercentage'],
                    'period' =>  $period,
                ],
                [
                    "icon" => "home",
                    "title" => "Total Group Ads",
                    "value" => array_sum($promotion_data['currentGroupAds']),
                    "class" => "primary",
                    "percentage" => $promotion_data['groupAdsChangePercentage'],
                    'period' =>  $period,
                ],
                [
                    "icon" => "receipt",
                    "title" => "Total Post Ad Revenue",
                    "value" => format_money(array_sum($promotion_data['currentPostAdRevenue'])),
                    "class" => "primary",
                    "percentage" => $promotion_data['postAdsRevenueChangePercentage'],
                    'period' =>  $period,
                ],
                [
                    "icon" => "home",
                    "title" => "Total Group Ad Revenue",
                    "value" => format_money(array_sum($promotion_data['currentGroupAdRevenue'])),
                    "class" => "primary",
                    "percentage" => $promotion_data['groupAdsRevenueChangePercentage'],
                    'period' =>  $period,
                ],
                [
                    "icon" => "users",
                    "title" => "Total Freemium User",
                    "value" => array_sum($promotion_data['currentFreemiumUser']),
                    "class" => "primary",
                    "percentage" => $promotion_data['freemiumUsersChangePercentage'],
                    'period' =>  $period,
                ],
                [
                    "icon" => "users",
                    "title" => "Total Premium User",
                    "value" => array_sum($promotion_data['currentPremiumUser']),
                    "class" => "primary",
                    "percentage" => $promotion_data['premiumUsersChangePercentage'],
                    'period' =>  $period,
                ]
            ],
            "dashboard_data" => $promotion_data,
        ];

        return $data;
    }

    public function getPromotionData($period = 'month')
    {
        // Set the current period and previous period based on the selected period
        switch ($period) {
            case 'day':
                $currentStartDate = Carbon::today();
                $previousStartDate = Carbon::yesterday();
                $interval = 'hour';
                $dataPoints = 24; // 24 hours in a day
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
        $postAdsChangePercentage = $this->calculatePercentageChange($currentData['total_post_ads'], $previousData['total_post_ads']);
        $groupAdsChangePercentage = $this->calculatePercentageChange($currentData['total_group_ads'], $previousData['total_group_ads']);
        $postAdsRevenueChangePercentage = $this->calculatePercentageChange($currentData['total_post_ads_revenue'], $previousData['total_post_ads_revenue']);
        $groupAdsRevenueChangePercentage = $this->calculatePercentageChange($currentData['total_group_ads_revenue'], $previousData['total_group_ads_revenue']);
        $freemiumUsersChangePercentage = $this->calculatePercentageChange($currentData['total_freemuim_users'], $previousData['total_freemuim_users']);
        $premiumUsersChangePercentage = $this->calculatePercentageChange($currentData['total_premium_users'], $previousData['total_premium_users']);

        return [
            'currentPostAds' => $currentData['total_post_ads'],
            'currentGroupAds' => $currentData['total_group_ads'],
            'currentPostAdRevenue' => $currentData['total_post_ads_revenue'],
            'currentGroupAdRevenue' => $currentData['total_group_ads_revenue'],
            'currentFreemiumUser' => $currentData['total_freemuim_users'],
            'currentPremiumUser' => $currentData['total_premium_users'],
            'postAdsChangePercentage' => $postAdsChangePercentage,
            'groupAdsChangePercentage' => $groupAdsChangePercentage,
            'postAdsRevenueChangePercentage' => $postAdsRevenueChangePercentage,
            'groupAdsRevenueChangePercentage' => $groupAdsRevenueChangePercentage,
            'freemiumUsersChangePercentage' => $freemiumUsersChangePercentage,
            'premiumUsersChangePercentage' => $premiumUsersChangePercentage,
        ];
    }

    private function fetchData($startDate, $interval, $dataPoints)
    {
        $promotions = Promotion::whereHas("payment");

        $total_post_ads = array_fill(0, $dataPoints, 0);
        $total_group_ads = array_fill(0, $dataPoints, 0);
        $total_post_ads_revenue = array_fill(0, $dataPoints, 0);
        $total_group_ads_revenue = array_fill(0, $dataPoints, 0);
        $total_freemuim_users = array_fill(0, $dataPoints, 0);
        $total_premium_users = array_fill(0, $dataPoints, 0);

        for ($i = 0; $i < $dataPoints; $i++) {
            $startOfInterval = $startDate->copy()->add($i, $interval);
            $endOfInterval = $startOfInterval->copy()->endOf($interval);

            $total_post_ads[$i] = $promotions->clone()->whereNotNull("post_id")
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

            $total_freemuim_users[$i] = User::whereDoesntHave("activeSubscription", function ($subscription) use ($startOfInterval, $endOfInterval) {
                $subscription->whereBetween('created_at', [$startOfInterval, $endOfInterval]);
            })->count();

            $total_premium_users[$i] = User::whereHas("activeSubscription", function ($subscription) use ($startOfInterval, $endOfInterval) {
                $subscription->whereBetween('created_at', [$startOfInterval, $endOfInterval]);
            })->count();
        }

        return [
            'total_post_ads' => $total_post_ads,
            'total_group_ads' => $total_group_ads,
            'total_post_ads_revenue' => $total_post_ads_revenue,
            'total_group_ads_revenue' => $total_group_ads_revenue,
            'total_freemuim_users' => $total_freemuim_users,
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
}
