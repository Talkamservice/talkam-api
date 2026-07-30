<?php

namespace App\Http\Resources\Business;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    /**
     * The company account as its own admin sees it. Deliberately explicit —
     * created_by and raw timestamps stay internal.
     */
    public function toArray(Request $request): array
    {
        return [
            "id" => $this->id,
            "name" => $this->name,
            "slug" => $this->slug,
            "domain" => $this->domain,
            "industry" => $this->industry,
            "headcount_band" => $this->headcount_band,
            "logo" => $this->logo,
            "hr_contact_email" => $this->hr_contact_email,
            "status" => $this->status,
            "seats_licensed" => (int) $this->seats_licensed,
            "seats_used" => $this->seatsUsed(),
            "therapist_access" => (bool) $this->therapist_access,
            "session_bundle_sessions" => (int) $this->session_bundle_sessions,
            "bundle_custom" => (bool) $this->bundle_custom,
            "per_employee_session_quota" => $this->per_employee_session_quota !== null
                ? (int) $this->per_employee_session_quota
                : null,
            "payment_timing" => $this->payment_timing ?? "prepay",
            "pay_method" => $this->pay_method,
            "bench_topics" => $this->bench_topics ?? [],
            "verified_at" => $this->verified_at?->toDateTimeString(),
            "is_verified" => $this->isVerified(),
            "created_at" => $this->created_at?->toDateTimeString(),
        ];
    }
}
