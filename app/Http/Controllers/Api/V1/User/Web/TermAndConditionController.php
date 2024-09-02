<?php

namespace App\Http\Controllers\Api\V1\User\Web;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\TermAndCondition\TermAndConditionResource;
use App\Models\TermAndCondition;
use App\Services\TermAndCondition\TermAndConditionService;
use Exception;
use Illuminate\Http\Request;

class TermAndConditionController extends Controller
{
    protected $term_and_condtion_service;

    public function __construct()
    {
        $this->term_and_condtion_service = new TermAndConditionService;
    }

    public function index(Request $request)
    {
        try {
            $term_and_condition = TermAndCondition::latest()->first();
            $data = !empty($term_and_condition) ? TermAndConditionResource::make($term_and_condition) : [];
            return ApiHelper::validResponse("Terms and conditions returned successfully", $data);
        } catch (Exception $e) {
            //throw $th;
            return ApiHelper::problemResponse("Something went wrong while trying to process your request", ApiConstants::SERVER_ERR_CODE,  $request, $e);
        }
    }

}
