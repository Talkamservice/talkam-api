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
            return ApiHelper::validResponse("Mood recorded successfully", [
                "mood" => $checkin->mood,
                "checked_in_on" => $checkin->checked_in_on,
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
            ]);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
