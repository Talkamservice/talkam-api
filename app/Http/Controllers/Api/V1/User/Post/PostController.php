<?php

namespace App\Http\Controllers\Api\V1\User\PostCategory;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Post\PostResource;
use App\Services\Post\PostService;
use Exception;
use Illuminate\Http\Request;

class PostController extends Controller
{
    protected $post_service;

    public function __construct()
    {
        $this->post_service = new PostService;
    }

    public function index(Request $request)
    {
        try {
            $categories = $this->post_service->list()->get();
            $data = PostResource::collection($categories);
            return ApiHelper::validResponse("Posts returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function show($id)
    {
        try {
            $user = $this->post_service->getById($id);
            $data = PostResource::make($user);
            return ApiHelper::validResponse("Post details returned successfully", $data);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }
}
