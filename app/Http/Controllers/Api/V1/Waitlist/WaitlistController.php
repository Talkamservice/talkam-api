<?php

namespace App\Http\Controllers\Api\V1\Waitlist;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\GeneralException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Waitlist\WaitlistResource;
use App\Models\Waitlist;
use App\Services\Waitlist\WaitlistService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WaitlistController extends Controller
{
    protected $waitlist_service;

    public function __construct()
    {
        $this->waitlist_service = new WaitlistService;
    }

    public function index(Request $request)
    {
        try {
            $waitlists = Waitlist::latest()->status()->get();
            $data = WaitlistResource::collection($waitlists);
            return ApiHelper::validResponse("Waitlists returned successfully", $data);
        } catch (Exception $e) {
            //throw $th;
            return ApiHelper::problemResponse("Something went wrong while trying to process your request", ApiConstants::SERVER_ERR_CODE,  $request, $e);
        }
    }

    public function save(Request $request)
    {
        try {
            $waitlist = $this->waitlist_service->create($request->all());
            $data = WaitlistResource::make($waitlist);
            return ApiHelper::validResponse("Waitlist suubmitted successfully", $data);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE,  $request, $e);
        } catch (GeneralException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE,  $request, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE,  $request, $e);
        }
    }
}
