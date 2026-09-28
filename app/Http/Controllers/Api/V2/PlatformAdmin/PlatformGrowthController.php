<?php

namespace App\Http\Controllers\Api\V2\PlatformAdmin;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\PlatformAdmin\PlatformGrowthService;
use Illuminate\Http\Request;
use Exception;

class PlatformGrowthController extends Controller
{
    public function index(Request $request)
    {
        try {
            $overview = PlatformGrowthService::overview($request->input('range', '12w'));
            return ApiHelper::validResponse("Growth overview returned successfully", $overview);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function aarrr(Request $request)
    {
        try {
            $data = PlatformGrowthService::aarrr($request->input('range', '12w'));
            return ApiHelper::validResponse("AARRR overview returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function funnel(Request $request)
    {
        try {
            $data = PlatformGrowthService::funnel($request->input('range', '12w'));
            return ApiHelper::validResponse("Conversion funnel returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function cohorts(Request $request)
    {
        try {
            $data = PlatformGrowthService::cohorts((int) $request->input('weeks', 8));
            return ApiHelper::validResponse("Cohort retention returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function segments(Request $request)
    {
        try {
            $data = PlatformGrowthService::segments();
            return ApiHelper::validResponse("Segments returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function segmentUsers(Request $request, string $key)
    {
        try {
            $data = PlatformGrowthService::segmentUsers(
                $key,
                (int) $request->input('page', 1),
                (int) $request->input('per_page', 20)
            );
            return ApiHelper::validResponse("Segment users returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
