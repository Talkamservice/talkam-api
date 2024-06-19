<?php

namespace App\Http\Controllers\Api\V1\User\Post;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Post\PostPollResource;
use App\Http\Resources\Post\PostResource;
use App\Services\Post\PostPollService;
use App\Services\Post\PostService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PostPollController extends Controller
{
    protected $post_service;
    protected $post_poll_service;

    public function __construct()
    {
        $this->post_service = new PostService;
        $this->post_poll_service = new PostPollService;
    }

    public function index(Request $request)
    {
        try {
            $post = $this->post_service->getById($request->post_id);
            $polls = $this->post_poll_service->list($post->id)->get();
            $data = PostPollResource::collection($polls);
            return ApiHelper::validResponse("Post polls returned successfully", $data);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function show($id)
    {
        try {
            $poll = $this->post_poll_service->getById($id);
            $data = PostPollResource::make($poll);
            return ApiHelper::validResponse("Post poll returned successfully", $data);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }

    public function store(Request $request)
    {
        try {
            $poll = $this->post_poll_service->select($request->all());
            $data = PostPollResource::make($poll);
            return ApiHelper::validResponse("Poll selected successfully", $data);
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
            $this->post_poll_service->delete($id);
            return ApiHelper::validResponse("Post poll deleted successfully");
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (InvalidRequestException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
