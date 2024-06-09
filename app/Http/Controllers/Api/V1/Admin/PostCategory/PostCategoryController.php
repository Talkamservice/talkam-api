<?php

namespace App\Http\Controllers\Api\V1\Admin\PostCategory;

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

    public function store(Request $request)
    {
        try {
            $user = $this->post_category_service->create($request->all());
            $data = PostCategoryResource::make($user);
            return ApiHelper::validResponse("Category created successfully", $data);
        } catch (ValidationException $th) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $th);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $user = $this->post_category_service->update($request->all(), $id);
            $data = PostCategoryResource::make($user);
            return ApiHelper::validResponse("Category updated successfully", $data);
        } catch (ValidationException $th) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $th);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $this->post_category_service->delete($id);
            return ApiHelper::validResponse("Category deleted successfully");
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (InvalidRequestException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
