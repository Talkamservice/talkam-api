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
        // Use the dashboard service class to get the dashboard data
        $dashboardData = $this->dashboard_service->getDashboardData();

        $data = [
            "cards" => [
                [
                    "icon" => "users",
                    "title" => "Total Users",
                    "value" => $dashboardData['totalUsersCurrentMonth'],
                    "class" => "primary",
                    "url" => route("admin.users.index"),
                    "percentage" => $dashboardData['usersChangePercentage']
                ],
                [
                    "icon" => "books",
                    "title" => "Total Categories",
                    "value" => $dashboardData['totalCategoriesCurrentMonth'],
                    "class" => "info",
                    "url" => "",
                    "percentage" => $dashboardData['categoriesChangePercentage']
                ],
                [
                    "icon" => "books",
                    "title" => "Total Posts",
                    "value" => $dashboardData['totalPostsCurrentMonth'],
                    "class" => "warning",
                    "url" => "",
                    "percentage" => $dashboardData['postsChangePercentage']
                ],
                [
                    "icon" => "books",
                    "title" => "Total Groups",
                    "value" => $dashboardData['totalGroupsCurrentMonth'],
                    "class" => "primary",
                    "url" => "",
                    "percentage" => $dashboardData['groupsChangePercentage']
                ]
            ],
            "users" => User::latest()->paginate(5),
            "activity_logs" => ActivityLog::latest()->limit(5)->get(),
        ];

        return view("dashboards.admin.pages.index", $data);
    }
}
