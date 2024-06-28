<?php

namespace Database\Seeders;

use App\Constants\Account\User\UserConstants;
use App\Services\Auth\AuthorizationService;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $data = [
            UserConstants::ADMIN => array_merge(
                $this->crud("user"),
                $this->slugifyPerms([
                    "login into admin dashboard",
                ]),
            ),
        ];


        foreach ($data as $key => $data_) {
            foreach ($data_ as  $perm) {
                Permission::firstOrCreate($perm);
            }
        }

        AuthorizationService::syncSudoRoles();
    }

    public function slugifyPerms(array $permissions, $guard = "web")
    {
        $perms = [];
        foreach ($permissions as $key => $perm) {
            $name = slugify("can " . $perm);
            $perms[] = [
                "name" => str_replace("-", "_", strtolower($name)),
                "guard_name" => $guard,
            ];
        }

        return $perms;
    }

    public function crud($model, array $actions = null, $guard = "web")
    {
        $list = [];
        foreach (["create", "read", "update", "delete"] as $action) {
            $list[] = [
                "name" => "can_" . $action . "_" . strtolower($model),
                "guard_name" => $guard,
            ];
        }
        return $list;
    }
}
