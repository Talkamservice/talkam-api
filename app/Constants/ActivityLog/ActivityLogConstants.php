<?php

namespace App\Constants\ActivityLog;

class ActivityLogConstants
{
    const EVENT_REQUESTED = "request";
    const EVENT_ACCEPTED = "accepted";
    const EVENT_CREATED = "created";
    const EVENT_UPDATED = "updated";
    const EVENT_DELETED = "deleted";
    const EVENT_AUTH_LOGIN = "login";
    const EVENT_AUTH_LOGOUT = "logout";
    const EVENT_TRANSFERRED = "transfer";
    const EVENT_RECEIVED = "received";
    const EVENT_UPLOADED = "uploaded";
    const EVENT_APPROVED = "approved";
    const EVENT_DECLINED = "declined";
    const EVENT_PRINTED = "printed";
    const EVENT_ESCALATED = "escalated";
    const EVENT_COMPLETED = "completed";
    const EVENT_HIDE_POST = "striked";
    const EVENT_STRIKED = "hide_post";
    const EVENT_POST_RESTORE = "restore_post";
    const EVENT_DATA_ERASED = "data_erased";
    const USER_ACCOUNT_DELETED = "account_deleted";
    const SUSPEND_USER = "suspend";
    const UNSUSPEND_USER = "unsuspend";
    const SENT = "sent";
    const SUSPENDED = "suspended";
    const ACTIVATED = "activated";
    const BANNED = "banned";
    const RESOLVED = "resolved";


    const EVENTS = [
        self::EVENT_ACCEPTED,
        self::EVENT_APPROVED,
        self::EVENT_DECLINED,
        self::EVENT_CREATED,
        self::EVENT_UPDATED,
        self::EVENT_DELETED,
        self::EVENT_AUTH_LOGIN,
        self::EVENT_AUTH_LOGOUT,
        self::EVENT_REQUESTED,
        self::EVENT_TRANSFERRED,
        self::EVENT_UPLOADED,
        self::EVENT_PRINTED,
        self::EVENT_RECEIVED,
        self::EVENT_COMPLETED,
        self::EVENT_ESCALATED,
        self::EVENT_STRIKED,
        self::EVENT_HIDE_POST,
        self::EVENT_POST_RESTORE,
        self::EVENT_DATA_ERASED,
        self::USER_ACCOUNT_DELETED,
        self::SUSPEND_USER,
        self::UNSUSPEND_USER,
        self::SENT,
        self::SUSPENDED,
        self::ACTIVATED,
        self::BANNED,
        self::RESOLVED,
    ];

    const CHANNEL_DEFAULT = "default";


    const CHANNELS = [
        self::CHANNEL_DEFAULT,
    ];


    const SOURCE_INTERNAL = "internal";
    const SOURCE_EXTERNAL = "external";

    const SOURCES = [
        self::SOURCE_INTERNAL => "Internal",
        self::SOURCE_EXTERNAL => "External",
    ];

    const API_URL_TYPE = "api";
    const SYSTEM_URL_TYPE = "system";

    const TYPES = [
        self::SYSTEM_URL_TYPE => "System",
        self::API_URL_TYPE => "API"
    ];

    public static function parseUrl($path, $type = self::SYSTEM_URL_TYPE)
    {
        if ($type == self::SYSTEM_URL_TYPE) {
            $url = env('APP_URL') . "/$path";
        } elseif ($type == self::API_URL_TYPE) {
            $url = env('APP_URL') . "/api/$path";
        }

        $position = strpos($url, '/');

        if ($position !== false) {
            $url = substr_replace($url, '', $position, 1);
        }

        return $url;
    }

    public static function canShowMetadata($log): bool
    {
        return in_array($log->activity, []);
    }
}
