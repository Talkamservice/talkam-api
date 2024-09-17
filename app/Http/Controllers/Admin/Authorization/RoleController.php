<?php

namespace App\Http\Controllers\Admin\Authorization;

use App\Constants\ActivityLog\ActivitiesConstants;
use App\Constants\ActivityLog\ActivityLogConstants;
use App\Constants\General\AppConstants;
use App\Constants\General\NotificationConstants;
use App\Http\Controllers\Controller;
use App\Services\ActivityLog\ActivityLogService;
use App\Services\Auth\AuthorizationService;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    
    public function index(Request $request)
    {
        $builder = Role::query();

        if (!empty($key = $request->search)) {
            $builder = $builder->where("name", "LIKE", "%$key%");
        }
        
        $roles = $builder->paginate(AppConstants::ADMIN_PAGINATION_SIZE);
        return view("dashboards.admin.pages.authorization.roles.index", [
            "roles" => $roles,
            "sn" => $roles->firstItem(),
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
        
        (new ActivityLogService)
            ->setEvent("created")
            ->setTitle("Created A Role")
            ->setDescription(auth()->user()?->full_name . " created a role")
            ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
            ->setActivity(ActivitiesConstants::CREATED_ROLE)
            ->setModel(Role::class, $role->id)
            ->setAdmin(auth()->user()?->id)
            ->setData(
                [
                    "Role" => $role->refresh()->toArray()
                ]
            )
            ->setUrl(request()->fullUrl())
            ->log();
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
        return view("dashboards.admin.pages.authorization.roles.permissions", [
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
        $role = Role::findorfail($id);

        $role->update($data);
        AuthorizationService::syncSudoRoles();

        (new ActivityLogService)
            ->setEvent("updated")
            ->setTitle("Updated A Role")
            ->setDescription(auth()->user()?->full_name . " updated a role")
            ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
            ->setActivity(ActivitiesConstants::UPDATED_ROLE)
            ->setModel(Role::class, $role->id)
            ->setAdmin(auth()->user()?->id)
            ->setData([
                "Role" => $role->refresh()->toArray()
            ])
            ->setUrl(request()->fullUrl())
            ->log();

        return back()->with(NotificationConstants::SUCCESS_MSG, "Role updated successfully!");
    }

    public function destroy($id)
    {
        $old_role = Role::findorfail($id);
        $role = Role::findorfail($id)->delete();
        (new ActivityLogService)
            ->setEvent("deleted")
            ->setTitle("Deleted A Role")
            ->setDescription(auth()->user()?->full_name . " deleted a role")
            ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
            ->setActivity(ActivitiesConstants::DELETED_ROLE)
            ->setModel(Role::class, $id)
            ->setAdmin(auth()->user()?->id)
            ->setData([
                "Role" => $old_role->toArray()
            ])
            ->setUrl(request()->fullUrl())
            ->log();
        return back()->with(NotificationConstants::SUCCESS_MSG, "Role deleted successfully!");
    }

    public function updatePermissions(Request $request, $id)
    {
        $role = Role::findById($id);
        $checkedPermissionIds = $request->checked_permissions ?? [];
        $permissions = Permission::whereIn("id", $checkedPermissionIds)->get();
        $role->syncPermissions($permissions);
        // Log the activity
        (new ActivityLogService)
            ->setEvent("updated")
            ->setTitle("Updated permissions")
            ->setDescription(auth()->user()?->full_name . " updated " . $role->name . "'s permissions")
            ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
            ->setActivity(ActivitiesConstants::UPDATED_PERMISSION)
            ->setModel(Role::class, $role->id)
            ->setAdmin(auth()->user()?->id)
            ->setData([
                "Permissions" => $permissions->toArray()
            ])
            ->setUrl(request()->fullUrl())
            ->log();
        return back()->with(NotificationConstants::SUCCESS_MSG, "Role permissions updated successfully!");
    }
}
