<?php

namespace App\Constants\Auth;

class PinConstants
{
    const SESSION_KEY = 'session_otp_verified';

    const TYPE_LOGIN = 'login';

    const TYPE_ACCOUNT_SETUP = 'account_setup';
    const TYPE_PASSWORD_RESET = 'password_reset';
    const TYPE_VERIFY_EMAIL = 'verify_email';
    // Business domain confirmation rides the same pin pipeline as mobile signup
    // verification but needs its own type so the two can render distinct email
    // templates and so verifyDomain()'s lookup can't be satisfied by a mobile
    // signup pin (or vice versa).
    const TYPE_VERIFY_EMAIL_BUSINESS = 'verify_email_business';

    const TITLES = [
        self::TYPE_PASSWORD_RESET => "Password Reset",
        self::TYPE_VERIFY_EMAIL => "Verify Email",
        self::TYPE_LOGIN => "Login Verification",
        self::TYPE_VERIFY_EMAIL_BUSINESS => "Verify Company Domain",
    ];

    const PASSWORD_REGEX = "^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]{8,32}$";
}
