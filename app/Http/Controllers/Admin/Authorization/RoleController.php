<?php

namespace App\Http\Controllers\Admin\Authorization;

use App\Constants\General\AppConstants;
use App\Constants\General\NotificationConstants;
use App\Http\Controllers\Controller;
use App\Services\Auth\AuthorizationService;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::paginate(AppConstants::ADMIN_PAGINATION_SIZE);
        $sn = $roles->firstItem();
        return view("dashboards.admin.pages.authorization.roles", [
            "roles" => $roles,
            "sn" => $sn,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            "name" => "required|string|unique:roles,name",
        ]);
        $data["name"] = str_replace(" ", "_", $data["name"]);
        $role = Role::create($data);
        AuthorizationService::enableLoginPermission($role);
        AuthorizationService::syncSudoRoles();
        return back()->with(NotificationConstants::SUCCESS_MSG, "Role created successfully!");
    }

    public function show(Request $request, $id)
    {
        $role = Role::findById($id);
        $builder = new Permission();

        if (!empty($key = $request->search)) {
            $name = str_replace("-", "_", slugify($key));
            $builder = $builder->where("name", "LIKE", "%$name%");
        }
        $permissions = $builder->get();
        return view("dashboards.authorization.roles.permissions", [
            "role" => $role,
            "permissions" => $permissions,
            "sn" => 1
        ]);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            "name" => "required|string|unique:roles,name,$id",
        ]);
        $data["name"] = str_replace(" ", "_", $data["name"]);
        Role::findorfail($id)->update($data);
        // AuthorizationService::syncSudoRoles();
        return back()->with(NotificationConstants::SUCCESS_MSG, "Role updated successfully!");
    }

    public function destroy($id)
    {
        Role::findorfail($id)->delete();
        return back()->with(NotificationConstants::SUCCESS_MSG, "Role deleted successfully!");
    }

    public function updatePermissions(Request $request, $id)
    {
        $role = Role::findById($id);
        $checkedPermissionIds = $request->checked_permissions ?? [];
        $permissions = Permission::whereIn("id", $checkedPermissionIds)->get();
        $role->syncPermissions($permissions);
        return back()->with(NotificationConstants::SUCCESS_MSG, "Role permissions updated successfully!");
    }
}
