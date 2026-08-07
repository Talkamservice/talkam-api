<?php

namespace App\Constants\Business;

/**
 * How a therapy session is paid for (web §09). Set on `therapy_sessions.coverage`
 * (default CONSUMER). Drives who pays, whether the bundle is drawn, and when the
 * therapist is credited.
 */
class SessionCoverageConstants
{
    const CONSUMER = 'consumer';       // non-B2B: the client pays, therapist paid on completion
    const ORG_BUNDLE = 'org_bundle';   // prepay company: drawn from the bundle, therapist paid on completion
    const ORG_METER = 'org_meter';     // postpay company: metered, therapist paid AFTER the company settles
    const ORG_EXTERNAL = 'org_external'; // company's OWN therapist: settled outside TalkAM, no TalkAM credit

    /** Booking-time signal: a prepaid bundle is exhausted under a block/top-up policy. */
    const BLOCKED = 'blocked';

    const PAYOUT_ON_COMPLETION = 'on_completion';
    const PAYOUT_ON_SETTLEMENT = 'on_settlement';
    const PAYOUT_EXTERNAL = 'external';
}
