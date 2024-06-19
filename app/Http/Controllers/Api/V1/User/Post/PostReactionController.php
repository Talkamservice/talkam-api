<?php

namespace App\Http\Controllers\Api\V1\User\Post;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Post\PostPollResource;
use App\Services\Post\PostReactionService;
use App\Services\Post\PostService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PostReactionController extends Controller
{
    protected $post_service;
    protected $post_reaction_service;

    public function __construct()
    {
        $this->post_service = new PostService;
        $this->post_reaction_service = new PostReactionService;
    }

    public function reaction(Request $request)
    {
        try {
            $response = $this->post_reaction_service->create($request->all());
            return ApiHelper::validResponse("Post reaction submitted successfully", $response);
        } catch (ValidationException $th) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $th);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }

    public function report(Request $request)
    {
        try {
            $response = $this->post_reaction_service->report($request->all());
            return ApiHelper::validResponse("Report submitted successfully");
        } catch (ValidationException $th) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $th);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }
}
