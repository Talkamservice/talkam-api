<?php

namespace App\Http\Controllers\Admin\Authorization;

use App\Constants\General\AppConstants;
use App\Constants\General\NotificationConstants;
use App\Http\Controllers\Controller;
use App\Services\Auth\AuthorizationService;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index()
    {
        $permissions = Permission::latest()->paginate(AppConstants::ADMIN_PAGINATION_SIZE);
        $sn = $permissions->firstItem();
        return view("dashboards.admin.pages.authorization.permissions.index" , [
            "permissions" => $permissions,
            "sn" => $sn,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            "name" => "required|string|unique:permissions,name",
        ]);
        $data["name"] = str_replace(" " , "_" , $data["name"]);
        $data["guard_name"] = "web";

        Permission::create($data);
        AuthorizationService::syncSudoRoles();
        return back()->with(NotificationConstants::SUCCESS_MSG, "Permission created successfully!");
    }


    public function update(Request $request, $id)
    {
        $data = $request->validate([
            "name" => "required|string|unique:permissions,name,$id",
            "guard_name" => "required|string|in:web,admin",
        ]);
        $data["name"] = str_replace(" " , "_" , $data["name"]);
        Permission::findorfail($id)->update($data);
        AuthorizationService::syncSudoRoles();
        return back()->with(NotificationConstants::SUCCESS_MSG, "Permission updated successfully!");
    }

    public function destroy($id)
    {
        Permission::findorfail($id)->delete();
        return back()->with(NotificationConstants::SUCCESS_MSG, "Permission deleted successfully!");
    }
}
