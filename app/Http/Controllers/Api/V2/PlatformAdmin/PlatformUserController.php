<?php

namespace App\Http\Controllers\Api\V2\PlatformAdmin;

use App\Constants\General\ApiConstants;
use App\Constants\General\StatusConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PlatformAdmin\PlatformUserService;
use App\Services\User\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Exception;

/**
 * Thin wrapper over UserService's existing suspend/ban/strike actions
 * (app/Http/Controllers/Admin/User/UserController.php's Blade equivalent) —
 * not role-restricted to consumers the way that Blade list is.
 */
class PlatformUserController extends Controller
{
    public function index(Request $request)
    {
        try {
            return ApiHelper::validResponse("Users returned successfully", [
                "users" => PlatformUserService::list($request),
                "type_counts" => PlatformUserService::typeCounts(),
            ]);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function show($id)
    {
        try {
            return ApiHelper::validResponse("User returned successfully", PlatformUserService::detail((int) $id));
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function sessions($id)
    {
        try {
            return ApiHelper::validResponse("Sessions returned successfully", PlatformUserService::sessions((int) $id));
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function mood($id)
    {
        try {
            return ApiHelper::validResponse("Mood summary returned successfully", PlatformUserService::mood((int) $id));
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function community($id)
    {
        try {
            return ApiHelper::validResponse("Community activity returned successfully", PlatformUserService::community((int) $id));
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function journey($id)
    {
        try {
            return ApiHelper::validResponse("Journey returned successfully", PlatformUserService::journey((int) $id));
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function activity($id)
    {
        try {
            return ApiHelper::validResponse("Event log returned successfully", \App\Services\PlatformAdmin\PlatformActivityLogService::forUser((int) $id));
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    /** Restricted to `name` — a platform admin correcting a display name is
     *  the only edit this page needs; everything else (email, password,
     *  interests…) stays a self-service or support-ticket action. */
    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                "name" => "required|string|max:150",
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $user = (new UserService)->update($validator->validated(), $id);
            return ApiHelper::validResponse("User updated", $user);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function destroy($id)
    {
        try {
            (new UserService)->delete($id);
            return ApiHelper::validResponse("User deleted", []);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function suspend(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                "duration" => "required|date|after:today",
                "suspend_reason" => "nullable|string|max:500",
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            (new UserService)->suspend($request, StatusConstants::INACTIVE, $id);
            return ApiHelper::validResponse("User suspended", User::find($id));
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function unsuspend(Request $request, $id)
    {
        try {
            (new UserService)->suspend($request, StatusConstants::ACTIVE, $id);
            return ApiHelper::validResponse("Suspension lifted", User::find($id));
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function ban(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                "suspend_ban_reason" => "required|string|max:500",
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            (new UserService)->ban($request, $id);
            return ApiHelper::validResponse("User banned", User::find($id));
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function strike($id)
    {
        try {
            $user = (new UserService)->strike($id);
            return ApiHelper::validResponse("Strike recorded", $user);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }
}
