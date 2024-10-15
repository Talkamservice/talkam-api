<?php

namespace App\Http\Controllers\Api\V1\User\Promotion;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Promotion\PromotionResource;
use App\Services\Promotion\PromotionService;
use Exception;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    protected $promotion_service;

    public function __construct()
    {
        $this->promotion_service = new PromotionService;
    }

    public function index(Request $request)
    {
        try {
            $promotions = $this->promotion_service->list($request->all())->get();
            $data = PromotionResource::collection($promotions);
            return ApiHelper::validResponse("Promotions returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function show($id)
    {
        try {
            $promotion = $this->promotion_service->getById($id);
            $data = PromotionResource::make($promotion);
            return ApiHelper::validResponse("Promotion details returned successfully", $data);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }

    public function submit($id)
    {
        try {
            $promotion = $this->promotion_service->create($id);
            $data = PromotionResource::make($promotion);
            return ApiHelper::validResponse("Promotion details returned successfully", $data);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }

    public function delete($id)
    {
        try {
            $promotion = $this->promotion_service->getById($id);
            $promotion->delete();
            return ApiHelper::validResponse("Promotion deleted successfully");
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }
}
