<?php

namespace App\Http\Controllers\Api\V2\User;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Users\UserResource;
use App\Services\User\MuteService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class MuteController extends Controller
{
    public $mute_service;
    function __construct()
    {
        $this->mute_service = new MuteService;
    }

    public function toggle(Request $request)
    {
        try {
            $muted = $this->mute_service->toggle(auth()->user(), $request->all());
            return ApiHelper::validResponse("Mute list updated successfully", [
                "muted" => $muted,
            ]);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (InvalidRequestException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function index()
    {
        try {
            $users = MuteService::muted(auth()->user())->get();
            return ApiHelper::validResponse("Muted users returned successfully", UserResource::customCollection($users));
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
