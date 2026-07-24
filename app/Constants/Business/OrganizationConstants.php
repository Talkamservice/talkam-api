<?php

namespace App\Constants\Business;

class OrganizationConstants
{
    /* Organization lifecycle */
    const STATUS_PENDING_VERIFICATION = "pending_verification";
    const STATUS_ACTIVE = "active";
    const STATUS_SUSPENDED = "suspended";

    const STATUSES = [
        self::STATUS_PENDING_VERIFICATION,
        self::STATUS_ACTIVE,
        self::STATUS_SUSPENDED,
    ];

    /* Membership roles — the three dashboards of the web platform (PRD §3) */
    const ROLE_ADMIN = "admin";
    const ROLE_EMPLOYEE = "employee";
    const ROLE_THERAPIST = "therapist";

    const ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_EMPLOYEE,
        self::ROLE_THERAPIST,
    ];

    /** Roles an admin may hand out on an invite. Admin seats are provisioned
     *  by TalkAM, never self-issued from the invite screen. */
    const INVITABLE_ROLES = [
        self::ROLE_EMPLOYEE,
        self::ROLE_THERAPIST,
    ];

    /* Membership lifecycle */
    const MEMBER_INVITED = "invited";
    const MEMBER_ACTIVE = "active";
    const MEMBER_INACTIVE = "inactive";

    const MEMBER_STATUSES = [
        self::MEMBER_INVITED,
        self::MEMBER_ACTIVE,
        self::MEMBER_INACTIVE,
    ];

    /** invitations.source marker for org invites (v1 uses "admin", §05 "group"). */
    const INVITE_SOURCE = "organization";

    /* Self check-in categories — "TalkAM B2B Auth.dc.html", self-check screen. */
    const SELF_CHECK_WORK = "work";
    const SELF_CHECK_ANXIETY = "anxiety";
    const SELF_CHECK_SLEEP = "sleep";
    const SELF_CHECK_RELATIONSHIPS = "relationships";

    const SELF_CHECK_CATEGORIES = [
        self::SELF_CHECK_WORK,
        self::SELF_CHECK_ANXIETY,
        self::SELF_CHECK_SLEEP,
        self::SELF_CHECK_RELATIONSHIPS,
    ];

    /** Human label used for the "we'll suggest therapists specialising in X" line. */
    const SELF_CHECK_CONCERNS = [
        self::SELF_CHECK_WORK => "Work Stress",
        self::SELF_CHECK_ANXIETY => "Anxiety",
        self::SELF_CHECK_SLEEP => "Sleep & Mood",
        self::SELF_CHECK_RELATIONSHIPS => "Relationships",
    ];

    const SELF_CHECK_MAX_SCORE = 3;
}
