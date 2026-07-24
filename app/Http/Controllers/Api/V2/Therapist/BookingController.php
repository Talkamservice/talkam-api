<?php

namespace App\Http\Controllers\Api\V2\Therapist;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\Therapist\SessionBookingService;
use App\Services\User\MemberSessionService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class BookingController extends Controller
{
    public $booking_service;
    function __construct()
    {
        $this->booking_service = new SessionBookingService;
    }

    public function store(Request $request)
    {
        try {
            $session = $this->booking_service->create(auth()->user(), $request->all());
            return ApiHelper::validResponse("Booking created successfully", SessionBookingService::detail($session));
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (ModelNotFoundException | InvalidRequestException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function initiatePayment(Request $request, $booking)
    {
        try {
            $payload = $this->booking_service->initiatePayment(auth()->user(), $booking, $request->all());
            return ApiHelper::validResponse("Payment initiated successfully", $payload);
        } catch (ModelNotFoundException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::NOT_FOUND_ERR_CODE, null, $e);
        } catch (InvalidRequestException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function index()
    {
        try {
            $user = auth()->user();
            // `upcoming` and `past` keep the exact §07 shape; the web
            // dashboard's summary strip rides alongside them.
            $data = SessionBookingService::listFor($user);
            $data["summary"] = MemberSessionService::summary($user);

            return ApiHelper::validResponse("Bookings returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    /**
     * Pre/post session mood, from the "before you join" and feedback modals.
     * Ownership goes through §07's getOwnedByUser, so another member's
     * session 404s.
     */
    public function sessionMood(Request $request, $booking)
    {
        try {
            $session = SessionBookingService::getOwnedByUser($booking, auth()->user());
            $session = (new MemberSessionService)->saveMood($session, $request->all());

            return ApiHelper::validResponse("Session mood saved successfully", [
                "id" => $session->id,
                "client_pre_mood" => $session->client_pre_mood,
                "client_post_mood" => $session->client_post_mood,
            ]);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (ModelNotFoundException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::NOT_FOUND_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function show($booking)
    {
        try {
            $session = SessionBookingService::getOwnedByUser($booking, auth()->user());
            return ApiHelper::validResponse("Booking returned successfully", SessionBookingService::detail($session));
        } catch (ModelNotFoundException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::NOT_FOUND_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
