<?php

namespace App\Http\Controllers\Api\V2\PlatformAdmin;

use App\Constants\General\ApiConstants;
use App\Constants\System\PlatformAdminConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Exception;

/**
 * Thin wrapper over Spatie roles — scoped to just the 4 real Platform Admin
 * roles (PlatformAdminConstants::ALL_ROLES), not the whole Spatie role
 * table (which also holds unrelated legacy admin roles).
 */
class PlatformRoleController extends Controller
{
    public function index()
    {
        try {
            // Manual count instead of Role::users() — that relation resolves
            // its target model from the role's guard config at query time,
            // which is one indirection too many for a page that just needs
            // "how many staff hold this role".
            $roles = Role::whereIn('name', PlatformAdminConstants::ALL_ROLES)->get(['id', 'name']);
            $counts = DB::table('model_has_roles')
                ->whereIn('role_id', $roles->pluck('id'))
                ->selectRaw('role_id, count(*) as aggregate')
                ->groupBy('role_id')
                ->pluck('aggregate', 'role_id');
            $roles->each(fn (Role $role) => $role->users_count = (int) ($counts[$role->id] ?? 0));

            $staff = User::role(PlatformAdminConstants::ALL_ROLES)
                ->with('roles:id,name')
                ->get(['id', 'first_name', 'last_name', 'email']);

            return ApiHelper::validResponse("Roles returned successfully", [
                "roles" => $roles,
                "staff" => $staff,
            ]);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function assign(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "user_id" => "required|exists:users,id",
                "role" => ["required", Rule::in(PlatformAdminConstants::ALL_ROLES)],
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $validated = $validator->validated();
            $user = User::findOrFail($validated['user_id']);
            $user->syncRoles([$validated['role']]);

            return ApiHelper::validResponse("Role assigned", $user->refresh()->load('roles:id,name'));
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function revoke(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "user_id" => "required|exists:users,id",
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $user = User::findOrFail($request->input('user_id'));
            $current = $user->getRoleNames()->intersect(PlatformAdminConstants::ALL_ROLES)->first();
            if ($current) {
                $user->removeRole($current);
            }

            return ApiHelper::validResponse("Role revoked", []);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
