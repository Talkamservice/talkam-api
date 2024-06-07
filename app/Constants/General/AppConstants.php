<?php

namespace App\Constants\General;

class AppConstants
{
    const REGISTER_BONUS = 250000;
    const ZERO = 0;
    const ONE = 1;

    const MAX_PROFILE_PIC_SIZE = 2048;

    const MALE = 'Male';
    const FEMALE = 'Female';


    const GENDERS = [
        self::MALE => self::MALE,
        self::FEMALE => self::FEMALE,
    ];

    const MONDAY = "Monday";
    const TUESDAY = "Tuesday";
    const WEDNESDAY = "Wednesday";
    const THURSDAY = "Thursday";
    const FRIDAY = "Friday";

    const PILL_CLASSES = [
        StatusConstants::COMPLETED => "success",
        StatusConstants::SUCCESSFUL => "success",
        StatusConstants::PENDING => "primary",
        StatusConstants::PROCESSING => "info",
        StatusConstants::ACTIVE => "success",
        StatusConstants::INACTIVE => "warning",
        StatusConstants::SKIPPED => "warning",
        StatusConstants::DECLINED => "danger",
        StatusConstants::DELETED => "danger",
        StatusConstants::CANCELLED => "danger",
        StatusConstants::FAILED => "danger",
    ];

    const ADMIN_PAGINATION_SIZE = 50;
    const API_PAGINATION_SIZE = 20;

    const BOOL_OPTIONS = [
        "1" => "Yes",
        "0" => "No"
    ];

    const WEEK_DAYS = [
      1 =>  self::MONDAY,
       2 => self::TUESDAY,
        self::WEDNESDAY,
        self::THURSDAY,
        self::FRIDAY,
    ];

    const PHONECODE_REGEX = "(\A[+]\d*$)";

    const BOOLEAN_OPTIONS = [
        true => "Yes",
        false => "No"
    ];
}
