<?php

namespace App\Constants\General;

class StatusConstants
{
    const ACTIVE = "Active";
    const AWAITING_APPROVAL = "Awaiting_Approval";
    const REVIEWING = "Reviewing";
    const INACTIVE = "Inactive";
    const CREATED = "Created";
    const STARTED = "Started";
    const APPROVED = "Approved";
    const SUSPENDED = "Suspended";
    const PENDING = "Pending";
    const UPCOMING = "Upcoming";
    const COMPLETED = "Completed";
    const CONFIRMED = "Comfirmed";
    const PROCESSING = "Processing";
    const CANCELLED = "Cancelled";
    const DECLINED = "Declined";
    const REFUNDED = "Refunded";
    const ROLLBACK = "Rollback";
    const ENDED = "Ended";
    const DELETED = "Deleted";
    const ARCHIVED = "Archived";
    const DRAFTED = "Drafted";
    const PUBLISHED = "Published";
    const SUCCESSFUL = "Successful";
    const FAILED = "Failed";
    const SKIPPED = "Skipped";
    const RESOLVED = "Resolved";
    const UNRESOLVED = "Unresolved";
    const VERIFIED = "Verified";
    const REJECTED = "Rejected";
    const USED = "Used";
    const UNUSED = "Unused";
    const ACTIVE_OPTIONS = [
        self::ACTIVE => "Active",
        self::INACTIVE => "Inactive",
    ];

    const BOOL_OPTIONS = [
        1 => "Yes",
        0 => "No",
    ];

    const WITHDRAWAL_OPTIONS = [
        self::PENDING => "Pending",
        self::PROCESSING => "Processing",
        self::COMPLETED => "Completed",
        self::DECLINED => "Declined",
    ];

    const TRANSACTION_STATUS_OPTIONS = [
        self::PENDING => "Pending",
        self::PROCESSING => "Processing",
        self::COMPLETED => "Completed",
        self::DECLINED => "Declined",
    ];

    const PAYMENT_STATUS_OPTIONS = [
        self::PENDING => "Pending",
        self::PROCESSING => "Processing",
        self::COMPLETED => "Completed",
        self::DECLINED => "Declined",
    ];

    const BANK_PAYMENT_STATUS_OPTIONS = [
        self::APPROVED => "Approved",
        self::DECLINED => "Declined",
    ];

    const ERROR_STATUS = [
        self::RESOLVED => "Resolved",
        self::UNRESOLVED => "Unresolved"
    ];
}

