<?php

namespace App\Http\Controllers\Api\V1\User\Web;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Faq\FaqResource;
use App\Models\Faq;
use App\Services\Faq\FaqService;
use Exception;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    protected $faq_service;

    public function __construct()
    {
        $this->faq_service = new FaqService;
    }

    public function index(Request $request)
    {
        try {
            $faqs = Faq::status()->get();
            $data = FaqResource::collection($faqs);
            return ApiHelper::validResponse("Faqs returned successfully", $data);
        } catch (Exception $e) {
            //throw $th;
            return ApiHelper::problemResponse("Something went wrong while trying to process your request", ApiConstants::SERVER_ERR_CODE,  $request, $e);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $faq = Faq::find($id);
            $data = !empty($faq) ? FaqResource::make($faq) : [];
            return ApiHelper::validResponse("Faq returned successfully", $data);
        } catch (Exception $e) {
            //throw $th;
            return ApiHelper::problemResponse("Something went wrong while trying to process your request", ApiConstants::SERVER_ERR_CODE,  $request, $e);
        }
    }

}
