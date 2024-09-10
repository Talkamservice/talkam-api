<?php
namespace App\Services\Dashboard;

use App\Models\Post;
use App\Models\PostCategory;
use App\Models\User;
use App\Constants\Account\User\UserConstants;
use App\Models\Group;
use Carbon\Carbon;

class DashboardService
{
    public function getDashboardData($period = 'month')
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
        // dd($dataPoints);

        // Fetch the current and previous period data
        $currentData = $this->fetchData($currentStartDate, $interval, $dataPoints);
        $previousData = $this->fetchData($previousStartDate, $interval, $dataPoints);

        // Calculate percentage changes
        $usersChangePercentage = $this->calculatePercentageChange($currentData['users'], $previousData['users']);
        $categoriesChangePercentage = $this->calculatePercentageChange($currentData['categories'], $previousData['categories']);
        $postsChangePercentage = $this->calculatePercentageChange($currentData['posts'], $previousData['posts']);
        $groupsChangePercentage = $this->calculatePercentageChange($currentData['groups'], $previousData['groups']);

        return [
            'currentUsers' => $currentData['users'],
            'currentCategories' => $currentData['categories'],
            'currentPosts' => $currentData['posts'],
            'currentGroups' => $currentData['groups'],
            'usersChangePercentage' => $usersChangePercentage,
            'categoriesChangePercentage' => $categoriesChangePercentage,
            'postsChangePercentage' => $postsChangePercentage,
            'groupsChangePercentage' => $groupsChangePercentage,
        ];
    }

    private function fetchData($startDate, $interval, $dataPoints)
    {
        $users = array_fill(0, $dataPoints, 0);
        $categories = array_fill(0, $dataPoints, 0);
        $posts = array_fill(0, $dataPoints, 0);
        $groups = array_fill(0, $dataPoints, 0);

        for ($i = 0; $i < $dataPoints; $i++) {
            $startOfInterval = $startDate->copy()->add($i, $interval);
            $endOfInterval = $startOfInterval->copy()->endOf($interval);

            // Debugging: Print start and end of interval
            // dd($startOfInterval, $endOfInterval);

            $users[$i] = User::where("role", UserConstants::USER)
                ->whereBetween('created_at', [$startOfInterval, $endOfInterval])
                ->count();

            $categories[$i] = PostCategory::status()
                ->whereBetween('created_at', [$startOfInterval, $endOfInterval])
                ->count();

            $posts[$i] = Post::status()
                ->whereBetween('created_at', [$startOfInterval, $endOfInterval])
                ->count();

            $groups[$i] = Group::status()
                ->whereBetween('created_at', [$startOfInterval, $endOfInterval])
                ->count();
        }

        // Debugging: Check data populated
        // dd($users, $categories, $posts, $groups);

        return [
            'users' => $users,
            'categories' => $categories,
            'posts' => $posts,
            'groups' => $groups,
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
