<?php

namespace App\Http\Controllers\Api\V2\User;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Users\UserResource;
use App\Services\User\FollowService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class FollowController extends Controller
{
    public $follow_service;
    function __construct()
    {
        $this->follow_service = new FollowService;
    }

    public function toggle(Request $request)
    {
        try {
            $following = $this->follow_service->toggle(auth()->user(), $request->all());
            return ApiHelper::validResponse("Subscription updated successfully", [
                "following" => $following,
            ]);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (InvalidRequestException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function following()
    {
        try {
            $users = FollowService::following(auth()->user())->get();
            return ApiHelper::validResponse("Following returned successfully", UserResource::customCollection($users));
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function followers()
    {
        try {
            $users = FollowService::followers(auth()->user())->get();
            return ApiHelper::validResponse("Followers returned successfully", UserResource::customCollection($users));
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
