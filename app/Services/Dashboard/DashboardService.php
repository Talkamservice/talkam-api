<?php

namespace App\Services\Dashboard;

use App\Models\Post;
use App\Models\PostCategory;
use App\Models\User;
use App\Constants\Account\User\UserConstants;

class DashboardService
{
    public function getDashboardData()
    {
        // Fetch data for the current month
        $currentMonth = now()->format('m');
        // Fetch data for the previous month
        $previousMonth = now()->subMonth()->format('m');

        // Calculate total users for the current and previous month
        $totalUsersCurrentMonth = User::where("role", UserConstants::USER)
            ->whereMonth('created_at', $currentMonth)
            ->count();
        $totalUsersPreviousMonth = User::where("role", UserConstants::USER)
            ->whereMonth('created_at', $previousMonth)
            ->count();
        $usersChangePercentage = $this->calculatePercentageChange($totalUsersCurrentMonth, $totalUsersPreviousMonth);

        // Calculate total categories for the current and previous month (including both parent and subcategories)
        $totalCategoriesCurrentMonth = PostCategory::status()
            ->whereMonth('created_at', $currentMonth)
            ->count();
        $totalCategoriesPreviousMonth = PostCategory::status()
            ->whereMonth('created_at', $previousMonth)
            ->count();
        $categoriesChangePercentage = $this->calculatePercentageChange($totalCategoriesCurrentMonth, $totalCategoriesPreviousMonth);

        // Calculate total posts for the current and previous month
        $totalPostsCurrentMonth = Post::status()
            ->whereMonth('created_at', $currentMonth)
            ->count();
        $totalPostsPreviousMonth = Post::status()
            ->whereMonth('created_at', $previousMonth)
            ->count();
        $postsChangePercentage = $this->calculatePercentageChange($totalPostsCurrentMonth, $totalPostsPreviousMonth);

        // Calculate total groups for the current and previous month (placeholder for now)
        $totalGroupsCurrentMonth = 0;
        $totalGroupsPreviousMonth = 0;
        $groupsChangePercentage = 0;

        return [
            'totalUsersCurrentMonth' => $totalUsersCurrentMonth,
            'totalUsersPreviousMonth' => $totalUsersPreviousMonth,
            'usersChangePercentage' => $usersChangePercentage,
            'totalCategoriesCurrentMonth' => $totalCategoriesCurrentMonth,
            'totalCategoriesPreviousMonth' => $totalCategoriesPreviousMonth,
            'categoriesChangePercentage' => $categoriesChangePercentage,
            'totalPostsCurrentMonth' => $totalPostsCurrentMonth,
            'totalPostsPreviousMonth' => $totalPostsPreviousMonth,
            'postsChangePercentage' => $postsChangePercentage,
            'totalGroupsCurrentMonth' => $totalGroupsCurrentMonth,
            'totalGroupsPreviousMonth' => $totalGroupsPreviousMonth,
            'groupsChangePercentage' => $groupsChangePercentage,
        ];
    }

    private function calculatePercentageChange($currentValue, $previousValue)
    {
        if ($previousValue == 0) {
            return $currentValue > 0 ? 100 : 0;
        }
        return round((($currentValue - $previousValue) / $previousValue) * 100, 2);
    }
}
