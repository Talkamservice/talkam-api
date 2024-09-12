<?php

namespace App\Http\Controllers\Api\V1\User\Post;

use App\Constants\General\ApiConstants;
use App\Constants\General\AppConstants;
use App\Constants\Post\PostConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Post\PostAttachmentResource;
use App\Http\Resources\Post\PostCommentResource;
use App\Http\Resources\Post\PostResource;
use App\Http\Resources\Post\TrendingResource;
use App\Models\PostAttachment;
use App\Services\Post\PostAttachmentService;
use App\Services\Post\PostService;
use App\Services\Post\RecentViewService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PostController extends Controller
{
    protected $post_service;
    protected $recent_view_service;

    public function __construct()
    {
        $this->post_service = new PostService;
        $this->recent_view_service = new RecentViewService;
    }

    public function index(Request $request)
    {
        try {
            $posts = $this->post_service->list($request->all())->status()->unblocked()->paginate(AppConstants::API_PAGINATION_SIZE)->appends($request->query());
            $data = collectPagination($posts);
            $data["data"] = PostResource::collection($data["data"]);
            return ApiHelper::validResponse("Posts returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function show($id)
    {
        try {
            $post = $this->post_service->getById($id);
            $this->recent_view_service->create(["post_id" => $post->id]);
            $data = PostResource::make($post);
            return ApiHelper::validResponse("Post details returned successfully", $data);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }

    public function store(Request $request)
    {
        try {
            $post = $this->post_service->create($request->all());
            $data = PostResource::make($post);
            return ApiHelper::validResponse("Post created successfully", $data);
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
            $post = $this->post_service->update($request->all(), $id);
            $data = PostResource::make($post);
            return ApiHelper::validResponse("Post updated successfully", $data);
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
            $this->post_service->delete($id);
            return ApiHelper::validResponse("Post deleted successfully");
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (InvalidRequestException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function trending(Request $request)
    {
        try {
            $trends = $this->post_service->trends($request->all())->distinct("tag")->whereNull("category_id")->where("count", ">", 1)->status()->orderByDesc("count")->get();
            $data = TrendingResource::collection($trends);
            return ApiHelper::validResponse("Trends returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function postWithComments(Request $request)
    {
        try {
            $posts = $this->post_service->getWithComments($request->all())->status()->unblocked()->paginate(AppConstants::API_PAGINATION_SIZE)->appends($request->query());
            $data = collectPagination($posts);
            $data["data"] = PostCommentResource::collection($data["data"]);
            return ApiHelper::validResponse("Posts returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function postWithLikes(Request $request)
    {
        try {
            $posts = $this->post_service->getWithLikes($request->all())->status()->latest("id")->unblocked()->paginate(AppConstants::API_PAGINATION_SIZE)->appends($request->query());
            $data = collectPagination($posts);
            $data["data"] = PostResource::collection($data["data"]);
            return ApiHelper::validResponse("Posts returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function media(Request $request)
    {
        try {
            $attachments = $this->post_service->getAllMedia($request->all());
            $data = collectPagination($attachments);
            $data["data"] = PostAttachmentResource::collection($data["data"]);
            return ApiHelper::validResponse("Media returned successfully", $data);
        } catch (ValidationException $th) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $th);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
