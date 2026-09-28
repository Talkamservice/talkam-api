<?php

namespace App\Http\Controllers\Api\V2\Therapist;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\Therapist\SessionNoteService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class SessionNoteController extends Controller
{
    public $session_note_service;
    function __construct()
    {
        $this->session_note_service = new SessionNoteService;
    }

    private function forbiddenUnlessTherapist()
    {
        if (empty(auth()->user()->therapist)) {
            return ApiHelper::problemResponse("Forbidden", ApiConstants::FORBIDDEN_ERR_CODE, null, null);
        }

        return null;
    }

    /**
     * §12 notes library: own notes, client filter + q search.
     */
    public function index(Request $request)
    {
        if ($forbidden = $this->forbiddenUnlessTherapist()) {
            return $forbidden;
        }

        try {
            $notes = SessionNoteService::library(auth()->user(), $request->all())
                ->paginate(\App\Constants\General\AppConstants::API_PAGINATION_SIZE)
                ->appends($request->query());

            $data = collectPagination($notes);
            $data["data"] = $notes->getCollection()->map(fn ($note) => array_merge($note->toArray(), [
                "client_id" => $note->session?->user_id,
            ]));

            return ApiHelper::validResponse("Notes returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function showById($note)
    {
        if ($forbidden = $this->forbiddenUnlessTherapist()) {
            return $forbidden;
        }

        try {
            $model = SessionNoteService::getOwnedNote(auth()->user(), $note);
            return ApiHelper::validResponse("Note returned successfully", $model->toArray());
        } catch (ModelNotFoundException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::NOT_FOUND_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function show($session)
    {
        try {
            $note = SessionNoteService::view(auth()->user(), $session);
            return ApiHelper::validResponse("Session note returned successfully", $note?->toArray());
        } catch (ModelNotFoundException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::NOT_FOUND_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function store(Request $request, $session)
    {
        try {
            $note = $this->session_note_service->write(auth()->user(), $session, $request->all());
            return ApiHelper::validResponse("Session note saved successfully", $note->toArray());
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (ModelNotFoundException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::NOT_FOUND_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
