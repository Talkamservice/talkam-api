<?php

namespace Database\Seeders;

use App\Constants\Account\User\UserConstants;
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
        if (!User::where("email", config("system.emails.sudo"))->exists()) {
            $user = (new RegistrationService)->create([
                "name" => "Sudo",
                "email" => config("system.emails.sudo"),
                "role" => UserConstants::ADMIN,
                "password" => 'Sys$+v#q20',
            ]);
        }
    }
}
