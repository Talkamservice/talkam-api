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

    /**
     * v2 list: paginated, per-member filters, genuinely-latest last_message
     * (v1 bug #3 fixed) and unread counts.
     */
    public function index(Request $request)
    {
        try {
            $viewer = auth()->user();
            $conversations = \App\Services\Messaging\V2\ConversationStateService::list($viewer, $request->all())
                ->paginate(\App\Constants\General\AppConstants::API_PAGINATION_SIZE)
                ->appends($request->query());

            $data = collectPagination($conversations);
            $data["data"] = $conversations->getCollection()
                ->map(fn ($c) => \App\Services\Messaging\V2\ConversationStateService::serialize($c, $viewer));

            return ApiHelper::validResponse("Conversations returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function pendingRequests()
    {
        try {
            $viewer = auth()->user();
            $conversations = \App\Services\Messaging\V2\ConversationStateService::pendingRequests($viewer)->get();

            return ApiHelper::validResponse(
                "Pending requests returned successfully",
                $conversations->map(fn ($c) => \App\Services\Messaging\V2\ConversationStateService::serialize($c, $viewer))->values()->all()
            );
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    /**
     * v2 report — validates conversation_id against conversations (v1 bug #4).
     */
    public function report(Request $request)
    {
        try {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                "conversation_id" => "required|exists:conversations,id",
                "message_id" => "nullable|exists:messages,id",
                "reason" => "required|string|max:2000",
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            \App\Services\Messaging\V2\ConversationStateService::memberRow(auth()->user(), $request->conversation_id);

            $report = \App\Models\ConversationReport::create([
                "user_id" => auth()->id(),
                "conversation_id" => $request->conversation_id,
                "message_id" => $request->message_id,
                "reason" => $request->reason,
            ]);

            return ApiHelper::validResponse("Report submitted successfully", ["id" => $report->id]);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (\App\Exceptions\General\ModelNotFoundException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::NOT_FOUND_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    /**
     * Per-member state actions: mute/unmute/archive/unarchive/star/unstar/seen.
     */
    public function state(Request $request, string $action)
    {
        try {
            $service = new \App\Services\Messaging\V2\ConversationStateService;
            $user = auth()->user();
            $id = $request->conversation_id;

            $member = match ($action) {
                "mute" => $service->mute($user, $id, $request->muted_until),
                "unmute" => $service->unmute($user, $id),
                "archive" => $service->setArchived($user, $id, true),
                "unarchive" => $service->setArchived($user, $id, false),
                "star" => $service->setStarred($user, $id, true),
                "unstar" => $service->setStarred($user, $id, false),
                "seen" => $service->seen($user, $id),
            };

            return ApiHelper::validResponse("Conversation updated successfully", [
                "is_muted" => (bool) $member->is_muted,
                "muted_until" => $member->muted_until ? (string) $member->muted_until : null,
                "archived_at" => $member->archived_at ? (string) $member->archived_at : null,
                "starred_at" => $member->starred_at ? (string) $member->starred_at : null,
                "last_seen_at" => $member->last_seen_at ? (string) $member->last_seen_at : null,
            ]);
        } catch (\App\Exceptions\General\ModelNotFoundException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::NOT_FOUND_ERR_CODE, null, $e);
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
