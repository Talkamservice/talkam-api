<?php

namespace App\Http\Controllers\Api\V2\User;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\Post\CommunityTrendingService;
use Illuminate\Http\Request;
use Exception;

class CommunityController extends Controller
{
    /**
     * Trending topics for the dashboard's Community tab.
     *
     * Anonymous by construction: topic, count, one excerpt and a relative
     * time. No author identity is serialized anywhere in this payload.
     */
    public function trending(Request $request)
    {
        try {
            $days = max(1, min((int) $request->input("days", 7), 90));

            return ApiHelper::validResponse("Trending topics returned successfully", [
                "topics" => CommunityTrendingService::topics(auth()->user(), $days),
                "window_days" => $days,
            ]);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
