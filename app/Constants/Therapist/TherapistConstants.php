<?php

namespace App\Constants\Therapist;

class TherapistConstants
{
    // Application lifecycle
    const STATUS_DRAFT = 'draft';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_IN_REVIEW = 'in_review';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    const ACTIVE_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
        self::STATUS_IN_REVIEW,
    ];

    // Step 2 document types
    const DOC_DEGREE = 'degree_certificate';
    const DOC_LICENCE = 'licence';
    const DOC_GOVERNMENT_ID = 'government_id';
    const DOC_INDEMNITY = 'indemnity_insurance';
    const DOC_HEADSHOT = 'headshot';

    const DOCUMENT_TYPES = [
        self::DOC_DEGREE,
        self::DOC_LICENCE,
        self::DOC_GOVERNMENT_ID,
        self::DOC_INDEMNITY,
        self::DOC_HEADSHOT,
    ];

    // Document review statuses
    const DOC_STATUS_PENDING = 'pending';
    const DOC_STATUS_APPROVED = 'approved';
    const DOC_STATUS_REJECTED = 'rejected';

    const DAYS_OF_WEEK = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
    ];

    const APPLICATION_STEPS = [
        'personal',
        'documents',
        'specialties',
        'availability',
        'payout',
    ];

    // Session formats
    const FORMAT_VIDEO = 'video';
    const FORMAT_VOICE = 'voice';
    const SESSION_FORMATS = [self::FORMAT_VIDEO, self::FORMAT_VOICE];

    // Therapy session lifecycle (planning doc 07/08)
    const SESSION_PENDING_PAYMENT = 'pending_payment';
    const SESSION_CONFIRMED = 'confirmed';
    const SESSION_IN_PROGRESS = 'in_progress';
    const SESSION_COMPLETED = 'completed';
    const SESSION_CANCELLED = 'cancelled';
    const SESSION_FAILED = 'failed';
    const SESSION_EXPIRED = 'expired';
    const SESSION_NO_SHOW = 'no_show';
}
