<?php

namespace App\Http\Controllers\Api\V2\Messaging;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\Messaging\V2\MessageActionService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class MessageActionController extends Controller
{
    public $action_service;
    function __construct()
    {
        $this->action_service = new MessageActionService;
    }

    private function respond(callable $action)
    {
        try {
            $result = $action();
            return ApiHelper::validResponse("Action completed successfully", $result);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (ModelNotFoundException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::NOT_FOUND_ERR_CODE, null, $e);
        } catch (InvalidRequestException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::FORBIDDEN_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function edit(Request $request)
    {
        return $this->respond(fn () => $this->action_service->edit(auth()->user(), $request->all())->toArray());
    }

    public function destroy(Request $request, $message)
    {
        return $this->respond(function () use ($request, $message) {
            $this->action_service->delete(auth()->user(), $message, $request->query("type", "for_me"));
            return null;
        });
    }

    public function reply(Request $request)
    {
        return $this->respond(fn () => $this->action_service->send(auth()->user(), $request->all())->toArray());
    }

    public function forward(Request $request)
    {
        return $this->respond(fn () => $this->action_service->forward(auth()->user(), $request->all())->toArray());
    }

    public function addReaction(Request $request)
    {
        return $this->respond(fn () => $this->action_service->addReaction(auth()->user(), $request->all())->toArray());
    }

    public function removeReaction(Request $request)
    {
        return $this->respond(function () use ($request) {
            $this->action_service->removeReaction(auth()->user(), $request->all());
            return null;
        });
    }

    public function pin(Request $request)
    {
        return $this->respond(fn () => $this->action_service->setPinned(auth()->user(), $request->message_id, true)->toArray());
    }

    public function unpin(Request $request)
    {
        return $this->respond(fn () => $this->action_service->setPinned(auth()->user(), $request->message_id, false)->toArray());
    }
}
