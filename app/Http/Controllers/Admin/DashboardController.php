<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    protected $dashboard_service;

    public function __construct(DashboardService $dashboard_service)
    {
        $this->dashboard_service = $dashboard_service;
    }

    public function index(Request $request)
    {
        $period = $request->get('period', 'month');
        // Ensure period is valid
        if (!in_array($period, ['day', 'week', 'month', 'year'])) {
            $period = 'month';
        }
        $dashboardData = $this->dashboard_service->getDashboardData($period);
        $subscriberData = $this->dashboard_service->getDashboardData();
        // dd($dashboardData);
        $data = [
            "cards" => [
                [
                    "icon" => "users",
                    "title" => "Total Users",
                    "value" => array_sum($dashboardData['currentUsers']),
                    "class" => "primary",
                    "url" => route("admin.users.index"),
                    "percentage" => $dashboardData['usersChangePercentage'],
                    'period' =>  $period,
                ],
                [
                    "icon" => "categories",
                    "title" => "Total Categories",
                    "value" => array_sum($dashboardData['currentCategories']),
                    "class" => "info",
                    "url" => route('admin.post-categories.index'),
                    "percentage" => $dashboardData['categoriesChangePercentage'],
                    'period' =>  $period,
                ],
                [
                    "icon" => "posts",
                    "title" => "Total Posts",
                    "value" => array_sum($dashboardData['currentPosts']),
                    "class" => "warning",
                    "url" => "",
                    "percentage" => $dashboardData['postsChangePercentage'],
                    'period' =>  $period,
                ],
                [
                    "icon" => "groups",
                    "title" => "Total Groups",
                    "value" => array_sum($dashboardData['currentGroups']),
                    "class" => "primary",
                    "url" => "",
                    "percentage" => $dashboardData['groupsChangePercentage'],
                    'period' =>  $period,
                ]
            ],
            "users" => User::latest()->paginate(5),
            "activity_logs" => ActivityLog::latest()->limit(5)->get(),
            "period" => $period,
            "dashboardData" => $dashboardData, // Pass raw data to the view
            "subscriptionCounts" => $dashboardData['subscriptionCounts'],
            "subscriptionRevenue" => $dashboardData['subscriptionRevenue'],
        ];

        return view('dashboards.admin.pages.index', $data);
    }
}
