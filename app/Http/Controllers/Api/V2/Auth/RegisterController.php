<?php

namespace App\Http\Controllers\Api\V2\Auth;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Users\UserResource;
use App\Services\Auth\V2\RegistrationService;
use App\Services\Therapist\TherapistApplicationService;
use App\Services\User\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Exception;

class RegisterController extends Controller
{
    public $register_service;
    public $application_service;
    function __construct()
    {
        $this->register_service = new RegistrationService;
        $this->application_service = new TherapistApplicationService;
    }

    public function register(Request $request)
    {
        DB::beginTransaction();
        try {
            $user = $this->register_service->create($request->all());
            $data["token"] = $user->createToken('auth')->plainTextToken;
            $data["user"] = UserResource::make($user);
            $this->register_service->postRegisterActions($user);
            DB::commit();
            return ApiHelper::validResponse("User registered successfully", $data);
        } catch (ValidationException $e) {
            DB::rollBack();
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            DB::rollBack();
            return ApiHelper::problemResponse(
                $this->serverErrorMessage,
                ApiConstants::SERVER_ERR_CODE,
                null,
                $e
            );
        }
    }

    /**
     * Registration + the therapist application's "personal" step, in one
     * call — a prospective therapist no longer has to register as a plain
     * user first and separately authenticate before onboarding can start.
     * The rest of the wizard (documents/specialties/availability/submit)
     * is unchanged and still runs against the token returned here.
     */
    public function registerTherapist(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'credential_type' => ['required', 'string', Rule::in(config('therapist.credential_types'))],
                'years_experience' => 'nullable|integer|min:0|max:80',
                // Scoped to this endpoint (not the shared registration
                // validation) so the existing /auth/register contract for
                // already-shipped clients doesn't change underneath them.
                'password' => 'confirmed',
            ], [
                'password.confirmed' => 'The password confirmation does not match.',
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            // Username isn't a therapist's own choice at signup time — the
            // shared registration validation still requires one present, so
            // generate it here rather than loosen that rule for /auth/register
            // too. A therapist can still send their own if they want it.
            $payload = $request->all();
            if (empty($payload['username'])) {
                $payload['username'] = UserService::generateUsername();
            }

            $user = $this->register_service->create($payload);
            $application = $this->application_service->savePersonal($user, $payload);

            $data["token"] = $user->createToken('auth')->plainTextToken;
            $data["user"] = UserResource::make($user);
            $data["application"] = [
                "application_id" => $application->id,
                "status" => $application->status,
                "steps" => TherapistApplicationService::stepCompleteness($application),
            ];

            $this->register_service->postRegisterActions($user);
            DB::commit();
            return ApiHelper::validResponse("Therapist registered and onboarding started", $data);
        } catch (ValidationException $e) {
            DB::rollBack();
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (InvalidRequestException $e) {
            DB::rollBack();
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            DB::rollBack();
            return ApiHelper::problemResponse(
                $this->serverErrorMessage,
                ApiConstants::SERVER_ERR_CODE,
                null,
                $e
            );
        }
    }
}
