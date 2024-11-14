<?php

namespace App\Services\Promotion;

use App\Models\Promotion;
use Carbon\Carbon;

class SinglePromotionService
{
    public function getPromotionData($promotionId, $period)
    {
        // Retrieve the promotion record from the database
        $promotion = Promotion::findOrFail($promotionId);

        // Adjust the start date based on the selected period
        switch ($period) {
            case 'year':
                $currentStartDate = Carbon::now()->startOfYear();
                $interval = 'month'; // Monthly intervals for the year
                $labels = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
                $dataPoints = 12;
                break;
            case 'month':
                $currentStartDate = Carbon::now()->startOfMonth();
                $interval = 'day'; // Daily intervals for the month
                $dataPoints = Carbon::now()->daysInMonth;
                $labels = range(1, $dataPoints); // Labels for each day of the month
                break;
            case 'week':
                $currentStartDate = Carbon::now()->startOfWeek();
                $interval = 'day'; // Daily intervals for the week
                $labels = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
                $dataPoints = 7;
                break;
            default: // Default to daily
                $currentStartDate = Carbon::today();
                $interval = 'hour'; // Hourly intervals for the day
                $dataPoints = 24;
                $labels = range(0, 23); // Labels for each hour
                break;
        }

        // Fetch data points for the specific promotion
        $promotionData = $this->fetchData($promotion, $currentStartDate, $interval, $dataPoints);

        return [
            'promotion_data' => $promotionData['promotion_data'],
            'data_labels' => $labels,
            'uuid' => $promotion->uuid,
        ];
    }

    private function fetchData($promotion, $startDate, $interval, $dataPoints)
    {
        $promotionData = array_fill(0, $dataPoints, 0); // Initialize data array with 0
        $labels = [];

        for ($i = 0; $i < $dataPoints; $i++) {
            $startOfInterval = $startDate->copy()->add($i, $interval);
            $endOfInterval = $startOfInterval->copy()->endOf($interval);

            // Fetch the promotion data for each interval (views, followers, etc.)
            $promotionCount = Promotion::where('id', $promotion->id)
                ->whereBetween('created_at', [$startOfInterval, $endOfInterval])
                ->count(); // Count the views (or followers) for the specific interval
               
            $promotionData[$i] = $promotionCount;
            $labels[] = $startOfInterval->format($interval === 'hour' ? 'H:i' : 'd M');
        }

        return [
            'promotion_data' => $promotionData, // 
            'data_labels' => $labels,
            'uuid' => $promotion->uuid,
        ];
    }
}
