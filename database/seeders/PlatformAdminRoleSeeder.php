<?php

namespace Database\Seeders;

use App\Constants\System\PlatformAdminConstants;
use App\Services\Auth\AuthorizationService;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

/**
 * Seeds the 4 real Spatie roles backing the Platform Admin panel
 * (App\Http\Middleware\EnsurePlatformRole / 'platform.role'). Access to
 * individual pages is enforced per-route (see routes/api_v2.php's
 * "platform-admin" group), not via fine-grained permissions here — each
 * role just needs to exist so it can be assigned to a User.
 */
class PlatformAdminRoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PlatformAdminConstants::ALL_ROLES as $role_name) {
            $role = AuthorizationService::getRoleByName($role_name);
            Permission::firstOrCreate(['name' => 'can_login_into_admin_dashboard', 'guard_name' => $role->guard_name]);
            $role->givePermissionTo('can_login_into_admin_dashboard');
        }
    }
}
