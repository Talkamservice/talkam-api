<?php

namespace App\Http\Controllers\Api\V1\User\Messaging;

use App\Constants\General\ApiConstants;
use App\Events\NewMessage;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Messaging\ConversationResource;
use App\Services\Messaging\ConversationService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ConversationController extends Controller
{
    protected $conversation_service;

    public function __construct()
    {
        $this->conversation_service = new ConversationService;
    }

    public function index(Request $request)
    {
        try {
            $categories = $this->conversation_service->list($request->all())->latest()->get();
            $data = ConversationResource::collection($categories);
            return ApiHelper::validResponse("Conversations returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function show($id)
    {
        try {
            $conversation = $this->conversation_service->show($id);
            $data = ConversationResource::make($conversation);
            return ApiHelper::validResponse("Conversation details returned successfully", $data);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }

    public function currentConversation(Request $request)
    {
        try {
            $conversation = $this->conversation_service->currentConversation($request->all());
            $data = ConversationResource::make($conversation);
            return ApiHelper::validResponse("Conversation fetched successfully", $data);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse("Something went wrong while trying to process your request", ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function store(Request $request)
    {
        try {
            $conversation = $this->conversation_service->create($request->all());
            $data = ConversationResource::make($conversation)->toArray($request);
            broadcast(new NewMessage($data["last_message"], $conversation->id))->toOthers();
            return ApiHelper::validResponse("Conversation created successfully", $data);
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
            $conversation = $this->conversation_service->update($request->all(), $id);
            $data = ConversationResource::make($conversation);
            return ApiHelper::validResponse("Conversation updated successfully", $data);
        } catch (ValidationException $th) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $th);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }

    public function updateStatus(Request $request)
    {
        try {
            $conversation = $this->conversation_service->updateStatus($request->all());
            $data = ConversationResource::make($conversation);
            return ApiHelper::validResponse("Conversation " . strtolower($request->status) . " successfully", $data);
        } catch (ValidationException $th) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $th);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }

    public function destroy($id)
    {
        try {
            $conversation = $this->conversation_service->getById($id);
            $conversation->delete();
            return ApiHelper::validResponse("Conversation deleted successfully");
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }

    public function report(Request $request)
    {
        try {
            $response = $this->conversation_service->report($request->all());
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
