<?php

namespace App\Services\Invitation;

use App\Models\Invitation;
use Throwable;

class AccessService
{
    public static function grant($member, Invitation $invite)
    {
        $role = $invite->role;
        $user = $member->user;

        $user->assignRole($role);
        $user->syncRoles([$role]);
    }
}
