<?php

namespace App\Http\Controllers\Api\V1\User\PostCategory;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\PostCategory\PostCategoryResource;
use App\Services\Post\RecentViewService;
use App\Services\PostCategory\PostCategoryService;
use Exception;
use Illuminate\Http\Request;

class PostCategoryController extends Controller
{
    protected $post_category_service;
    protected $recent_view_service;

    public function __construct()
    {
        $this->post_category_service = new PostCategoryService;
        $this->recent_view_service = new RecentViewService;
    }

    public function index(Request $request)
    {
        try {
            $categories = $this->post_category_service->list($request->all())->get();
            $data = PostCategoryResource::collection($categories);
            return ApiHelper::validResponse("Categories returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function show($id)
    {
        try {
            $category = $this->post_category_service->getById($id);
            $this->recent_view_service->create(["category_id" => $category->id]);
            $data = PostCategoryResource::make($category);
            return ApiHelper::validResponse("Category details returned successfully", $data);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }

    public function subCategories(Request $request)
    {
        try {
            $categories = $this->post_category_service->subCategoryList($request->all())->get();
            $data = PostCategoryResource::collection($categories);
            return ApiHelper::validResponse("Sub categories returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

}
