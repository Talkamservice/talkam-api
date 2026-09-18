<?php

namespace App\Http\Resources\Business;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationInvitationResource extends JsonResource
{
    /**
     * An invite row on the admin roster.
     *
     * `uuid` is NEVER emitted: it is the single-use accept credential and only
     * the invite email carries it. Admins resend and revoke by row id.
     */
    public function toArray(Request $request): array
    {
        return [
            "id" => $this->id,
            "email" => $this->invitee_email,
            "role" => $this->invite_role,
            "department" => $this->department,
            "status" => $this->status,
            "invited_by" => $this->whenLoaded("inviter", fn () => [
                "id" => $this->inviter?->id,
                "name" => trim(($this->inviter?->first_name ?? "") . " " . ($this->inviter?->last_name ?? "")) ?: null,
                "email" => $this->inviter?->email,
            ]),
            "sent_at" => $this->created_at?->toDateTimeString(),
            "opened_at" => $this->opened_at ? (string) $this->opened_at : null,
            "joined_at" => $this->response_date ? (string) $this->response_date : null,
            "expires_at" => $this->invite_expires_at ? (string) $this->invite_expires_at : null,
            "revoked_at" => $this->revoke_at ? (string) $this->revoke_at : null,
        ];
    }
}
