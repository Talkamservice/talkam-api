<?php

namespace App\Http\Controllers\Api\V1\User\Web;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Faq\FaqResource;
use App\Http\Resources\FaqCategory\FaqCategoryResource;
use App\Models\Faq;
use App\Models\FaqCategory;
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
            $faqs = FaqCategory::status()->get();
            $data = FaqCategoryResource::collection($faqs);
            return ApiHelper::validResponse("Faqs returned successfully", $data);
        } catch (Exception $e) {
            //throw $th;
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE,  $request, $e);
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
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE,  $request, $e);
        }
    }

}
