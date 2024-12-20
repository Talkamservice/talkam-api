<?php

namespace App\Http\Controllers\Api\V1\User\Web;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentTerm\PaymentTermResource;
use App\Models\PaymentTerm;
use App\Services\Finance\Payment\PaymentTermService;
use Exception;
use Illuminate\Http\Request;

class PaymentTermsController extends Controller
{
    protected $payment_term_service;

    public function __construct()
    {
        $this->payment_term_service = new PaymentTermService;
    }

    public function index(Request $request)
    {
        try {
            $payment_term = PaymentTerm::latest()->first();
            $data = !empty($payment_term) ? PaymentTermResource::make($payment_term) : [];
            return ApiHelper::validResponse("Payment terms returned successfully", $data);
        } catch (Exception $e) {
            //throw $th;
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE,  $request, $e);
        }
    }
}
