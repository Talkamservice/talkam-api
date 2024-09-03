<?php

namespace App\Http\Controllers\Api\V1\User\Web;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\PrivacyPolicy\PrivacyPolicyResource;
use App\Models\PrivacyPolicy;
use App\Services\PrivacyPolicy\PrivacyPolicyService;
use Exception;
use Illuminate\Http\Request;

class PrivacyPolicyController extends Controller
{
    protected $privacy_policy_service;

    public function __construct()
    {
        $this->privacy_policy_service = new PrivacyPolicyService;
    }

    public function index(Request $request)
    {
        try {
            $privacy_policy = PrivacyPolicy::latest()->first();
            $data = !empty($privacy_policy) ? PrivacyPolicyResource::make($privacy_policy) : [];
            return ApiHelper::validResponse("Privacy policy returned successfully", $data);
        } catch (Exception $e) {
            //throw $th;
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE,  $request, $e);
        }
    }

}
