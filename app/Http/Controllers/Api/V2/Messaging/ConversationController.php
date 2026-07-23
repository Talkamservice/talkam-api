<?php

namespace App\Http\Controllers\Api\V2\Messaging;

use App\Constants\General\ApiConstants;
use App\Constants\General\StatusConstants;
use App\Constants\Therapist\TherapistConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Messaging\ConversationResource;
use App\Models\Therapist;
use App\Models\TherapySession;
use App\Services\Messaging\ConversationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class ConversationController extends Controller
{
    public $conversation_service;
    function __construct()
    {
        $this->conversation_service = new ConversationService;
    }

    /**
     * v2 thread opening: therapist<->client pairs with a confirmed booking
     * bypass the AWAITING_RESPONSE request model; community DMs keep it.
     */
    public function store(Request $request)
    {
        try {
            $conversation = $this->conversation_service->create($request->all());

            if ($conversation->status == StatusConstants::AWAITING_RESPONSE
                && $this->isBookingPair(auth()->id(), $request->input("receiver_id"))) {
                $conversation->update(["status" => StatusConstants::ACTIVE]);
            }

            return ApiHelper::validResponse(
                "Conversation created successfully",
                ConversationResource::make($conversation->refresh())
            );
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (InvalidRequestException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    private function isBookingPair($user_a, $user_b): bool
    {
        if (empty($user_a) || empty($user_b)) {
            return false;
        }

        $statuses = [
            TherapistConstants::SESSION_CONFIRMED,
            TherapistConstants::SESSION_IN_PROGRESS,
            TherapistConstants::SESSION_COMPLETED,
        ];

        foreach ([[$user_a, $user_b], [$user_b, $user_a]] as [$client_id, $therapist_user_id]) {
            $therapist = Therapist::where("user_id", $therapist_user_id)->first();
            if (empty($therapist)) {
                continue;
            }

            $exists = TherapySession::where("therapist_id", $therapist->id)
                ->where("user_id", $client_id)
                ->whereIn("status", $statuses)
                ->exists();

            if ($exists) {
                return true;
            }
        }

        return false;
    }
}
