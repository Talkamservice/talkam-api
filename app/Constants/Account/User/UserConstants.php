<?php

namespace App\Constants\Account\User;

use App\Constants\General\StatusConstants;

class UserConstants
{
    const SUDO = "Sudo";
    const OWNER = "Owner";
    const USER = "User";
    const ADMIN = "Admin";
    const THERAPIST = "Therapist";
    const MEMBER = "Member";
    const AMBASSADOR = "Ambassador";
    const SUPER_ADMIN = "Super Admin";
    const COMMUNITY_MANAGER = "Community Manager";
    const MODERATOR = "Moderator";

    const GREEN = "Green";
    const YELLOW = "Yellow";
    const ORANGE = "Orange";
    const RED = "Red";

    const LEVEL_ONE = "Level 1";
    const LEVEL_TWO = "Level 2";
    const LEVEL_THREE = "Level 3";

    const UPDATE_VERIFICATION_STATUS = "UPDATE_VERIFICATION_STATUS";

    const ROLES = [
        self::OWNER,
        self::ADMIN,
        // self::MEMBER,
        self::USER
    ];

    const NON_OWNER_ROLES = [
        self::ADMIN,
        self::MEMBER,
    ];

    const STATUSES = [
        StatusConstants::PENDING,
        StatusConstants::ACTIVE,
        StatusConstants::INACTIVE,
    ];

    const ACCOUNT_LEVELS = [
        self::LEVEL_ONE => self::LEVEL_ONE,
        self::LEVEL_TWO => self::LEVEL_TWO,
        self::LEVEL_THREE => self::LEVEL_THREE,
    ];

    const GROUP_ROLES = [
        self::OWNER => self::OWNER,
        self::ADMIN => self::ADMIN,
        self::COMMUNITY_MANAGER => self::COMMUNITY_MANAGER,
        self::MODERATOR => self::MODERATOR,
        self::MEMBER => self::MEMBER,
    ];

    /** Roles an admin can hand a member via the platform-admin panel —
     *  Owner is excluded, since reassigning it is a separate transfer-of-
     *  ownership operation, not a routine role change. */
    const ADMIN_ASSIGNABLE_GROUP_ROLES = [
        self::ADMIN,
        self::COMMUNITY_MANAGER,
        self::MODERATOR,
        self::MEMBER,
    ];
}
