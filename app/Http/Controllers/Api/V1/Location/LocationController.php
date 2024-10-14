<?php

namespace App\Http\Controllers\Api\V1\Location;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Location\CountryResource;
use App\Http\Resources\Location\StateResource;
use App\Models\Country;
use App\Models\State;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function countries(Request $request)
    {
        try {
            $builder = Country::query();

            if (!empty($key = $request->search)) {
                $builder = $builder->search($key);
            }

            $cities = $builder->get();
            $data = CountryResource::collection($cities);
            return ApiHelper::validResponse("Countries returned successfully", $data);
        } catch (\Exception $e) {
            $message = 'Something went wrong while processing your request.';
            return ApiHelper::problemResponse($message, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }


    public function states(Request $request)
    {
        try {
            $builder = State::query();
            if (!empty($key = $request->country_id)) {
                $builder = $builder->where("country_id", $key);
            }
            if (!empty($key = $request->search)) {
                $builder = $builder->search($key);
            }
            $states = $builder->get();
            $data = StateResource::collection($states);
            return ApiHelper::validResponse("States returned successfully", $data);
        } catch (\Exception $e) {
            $message = 'Something went wrong while processing your request.';
            return ApiHelper::problemResponse($message, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
