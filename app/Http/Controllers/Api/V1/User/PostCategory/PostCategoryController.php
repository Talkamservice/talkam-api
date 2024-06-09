<?php

namespace App\Http\Controllers\Api\V1\User\PostCategory;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\PostCategory\PostCategoryResource;
use App\Services\PostCategory\PostCategoryService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PostCategoryController extends Controller
{
    protected $post_category_service;

    public function __construct()
    {
        $this->post_category_service = new PostCategoryService;
    }

    public function index(Request $request)
    {
        try {
            $categories = $this->post_category_service->list()->get();
            $data = PostCategoryResource::collection($categories);
            return ApiHelper::validResponse("Categories returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function show($id)
    {
        try {
            $user = $this->post_category_service->getById($id);
            $data = PostCategoryResource::make($user);
            return ApiHelper::validResponse("Category details returned successfully", $data);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }
}
