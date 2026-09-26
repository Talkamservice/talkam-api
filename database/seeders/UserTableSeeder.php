<?php

namespace Database\Seeders;

use App\Constants\Account\User\UserConstants;
use App\Constants\System\PlatformAdminConstants;
use App\Models\User;
use App\Services\Auth\RegistrationService;
use Illuminate\Database\Seeder;

class UserTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::where("email", config("system.emails.sudo"))->first();

        if (empty($user)) {
            $user = (new RegistrationService)->create([
                "name" => "Sudo",
                "email" => config("system.emails.sudo"),
                "role" => UserConstants::ADMIN,
                "password" => 'Sys$+v#q20',
            ]);
        }

        // Also gives it the Super Admin platform role, so it can log into
        // the /platform panel (App\Http\Middleware\EnsurePlatformRole) —
        // this only assigns a role, never re-runs registration for an
        // already-existing user.
        $user->assignRole(PlatformAdminConstants::ROLE_SUPER_ADMIN);
    }
}
 