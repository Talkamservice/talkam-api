<?php

namespace App\Constants\System;

class PlatformAdminConstants
{
    const ROLE_SUPER_ADMIN = "Super Admin";
    const ROLE_ADMIN = "Admin";
    const ROLE_CONTENT_MANAGER = "Content Manager";
    const ROLE_SUPPORT_STAFF = "Support Staff";

    const ALL_ROLES = [
        self::ROLE_SUPER_ADMIN,
        self::ROLE_ADMIN,
        self::ROLE_CONTENT_MANAGER,
        self::ROLE_SUPPORT_STAFF,
    ];
}
