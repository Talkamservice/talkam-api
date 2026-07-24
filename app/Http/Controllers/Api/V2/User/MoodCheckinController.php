<?php

namespace App\Http\Controllers\Api\V2\User;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\User\MoodCheckinService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class MoodCheckinController extends Controller
{
    public $mood_checkin_service;
    function __construct()
    {
        $this->mood_checkin_service = new MoodCheckinService;
    }

    public function store(Request $request)
    {
        try {
            $checkin = $this->mood_checkin_service->checkIn(auth()->user(), $request->all());
            // The original §03 keys stay exactly where they were — the web
            // additions ride alongside in `checkin`.
            return ApiHelper::validResponse("Mood recorded successfully", [
                "mood" => $checkin->mood,
                "checked_in_on" => $checkin->checked_in_on,
                "checkin" => MoodCheckinService::serialize($checkin),
            ]);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function today()
    {
        try {
            $checkin = MoodCheckinService::today(auth()->user());
            return ApiHelper::validResponse("Mood check-in state returned successfully", [
                "checked_in" => !empty($checkin),
                "mood" => $checkin?->mood,
                "checked_in_on" => $checkin?->checked_in_on,
                "checkin" => MoodCheckinService::serialize($checkin),
                "factors" => config("v2.checkins.factors"),
            ]);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    /** Paginated history — the "Recent check-ins" list. */
    public function index(Request $request)
    {
        try {
            $checkins = MoodCheckinService::history(auth()->user())
                ->paginate($request->input("per_page", ApiConstants::PAGINATION_SIZE_API));

            $data = $checkins->toArray();
            $data["data"] = collect($checkins->items())
                ->map(fn ($row) => MoodCheckinService::serialize($row))
                ->all();

            return ApiHelper::validResponse("Check-ins returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    /**
     * Streak, average, days logged, month delta, the day-by-day series and the
     * top factors — everything both dashboard charts and stat strips need.
     */
    public function summary(Request $request)
    {
        try {
            $days = (int) $request->input("days", config("v2.checkins.trend_days"));
            $days = max(1, min($days, 366));

            return ApiHelper::validResponse(
                "Mood summary returned successfully",
                MoodCheckinService::summary(auth()->user(), $days)
            );
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
