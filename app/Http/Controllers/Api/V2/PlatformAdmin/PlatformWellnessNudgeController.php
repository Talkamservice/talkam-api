<?php

namespace App\Http\Controllers\Api\V2\PlatformAdmin;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\WellnessNudgeMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Exception;

/**
 * Admin CRUD for the pool of texts the daily wellness check-in nudge picks
 * from at random (see WellnessCheckinNudgeNotification).
 */
class PlatformWellnessNudgeController extends Controller
{
    public function index()
    {
        try {
            $messages = WellnessNudgeMessage::orderBy("id")->get()->map(fn ($m) => $this->serialize($m));

            return ApiHelper::validResponse("Wellness nudge messages returned successfully", $messages);
        } catch (Exception $e) {
            return $this->failure($e);
        }
    }

    public function store(Request $request)
    {
        try {
            $data = $this->validated($request->all());

            return ApiHelper::validResponse("Wellness nudge message created successfully", $this->serialize(WellnessNudgeMessage::create($data)));
        } catch (Exception $e) {
            return $this->failure($e);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $message = WellnessNudgeMessage::find($id);
            if (empty($message)) {
                return ApiHelper::problemResponse("Wellness nudge message not found", ApiConstants::NOT_FOUND_ERR_CODE, null, null);
            }

            $message->update($this->validated($request->all(), true));

            return ApiHelper::validResponse("Wellness nudge message updated successfully", $this->serialize($message->refresh()));
        } catch (Exception $e) {
            return $this->failure($e);
        }
    }

    public function destroy($id)
    {
        try {
            $message = WellnessNudgeMessage::find($id);
            if (empty($message)) {
                return ApiHelper::problemResponse("Wellness nudge message not found", ApiConstants::NOT_FOUND_ERR_CODE, null, null);
            }

            $message->delete();

            return ApiHelper::validResponse("Wellness nudge message deleted successfully");
        } catch (Exception $e) {
            return $this->failure($e);
        }
    }

    private function validated(array $data, bool $partial = false): array
    {
        $required = $partial ? "sometimes|required" : "required";

        $validator = Validator::make($data, [
            "title" => "sometimes|required|string|max:120",
            "message" => "$required|string|max:255",
            "is_active" => "sometimes|boolean",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    private function serialize(WellnessNudgeMessage $message): array
    {
        return [
            "id" => $message->id,
            "title" => $message->title,
            "message" => $message->message,
            "is_active" => $message->is_active,
            "created_at" => formatDate($message->created_at),
            "updated_at" => formatDate($message->updated_at),
        ];
    }

    private function failure(Exception $e)
    {
        if ($e instanceof ValidationException) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        }

        return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
    }
}
