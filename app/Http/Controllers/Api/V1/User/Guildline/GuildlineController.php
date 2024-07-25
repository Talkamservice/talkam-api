<?php

namespace App\Http\Controllers\Api\V1\User\Guildline;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Guildline\GuildlineResource;
use App\Services\Guildline\GuildlineService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GuildlineController extends Controller
{
    protected $guildline_service;

    public function __construct()
    {
        $this->guildline_service = new GuildlineService;
    }

    public function index(Request $request)
    {
        try {
            $guildlines = $this->guildline_service->list($request->all())->get();
            $data = GuildlineResource::collection($guildlines);
            return ApiHelper::validResponse("Guildlines returned successfully", $data);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function show($id)
    {
        try {
            $guildline = $this->guildline_service->getById($id);
            $data = GuildlineResource::make($guildline);
            return ApiHelper::validResponse("Guildline returned successfully", $data);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }

    public function store(Request $request)
    {
        try {
            $guildline = $this->guildline_service->create($request->all());
            $data = GuildlineResource::make($guildline);
            return ApiHelper::validResponse("Guildline created successfully", $data);
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
            $guildline = $this->guildline_service->update($request->all(), $id);
            $data = GuildlineResource::make($guildline);
            return ApiHelper::validResponse("Guildline updated successfully", $data);
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
            $this->guildline_service->delete($id);
            return ApiHelper::validResponse("Guildline deleted successfully");
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (InvalidRequestException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
