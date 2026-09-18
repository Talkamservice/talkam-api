<?php

namespace App\Http\Controllers\Api\V2\Messaging;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\MessageDraft;
use App\Services\Messaging\V2\ConversationStateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Exception;

class DraftController extends Controller
{
    public function save(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "conversation_id" => "required|exists:conversations,id",
                "content" => "required|string|max:" . config("v2.messaging.max_length"),
                "replied_to_message_id" => "nullable|exists:messages,id",
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $user = auth()->user();
            ConversationStateService::memberRow($user, $request->conversation_id);

            $draft = MessageDraft::updateOrCreate([
                "user_id" => $user->id,
                "conversation_id" => $request->conversation_id,
            ], [
                "content" => $request->content,
                "replied_to_message_id" => $request->replied_to_message_id,
            ]);

            return ApiHelper::validResponse("Draft saved successfully", $draft->toArray());
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (ModelNotFoundException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::NOT_FOUND_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function get(Request $request)
    {
        try {
            $draft = MessageDraft::where([
                "user_id" => auth()->id(),
                "conversation_id" => $request->conversation_id,
            ])->first();

            return ApiHelper::validResponse("Draft returned successfully", $draft?->toArray());
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function delete(Request $request)
    {
        try {
            MessageDraft::where([
                "user_id" => auth()->id(),
                "conversation_id" => $request->conversation_id,
            ])->delete();

            return ApiHelper::validResponse("Draft deleted successfully");
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
