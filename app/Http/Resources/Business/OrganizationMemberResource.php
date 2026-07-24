<?php

namespace App\Http\Resources\Business;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationMemberResource extends JsonResource
{
    /**
     * A seat on the admin roster.
     *
     * Identity only — name, email, role, department, seat status. No mood,
     * session, message or community data ever rides on this resource; that is
     * the privacy boundary the whole admin lane is built around (PRD §12).
     */
    public function toArray(Request $request): array
    {
        return [
            "id" => $this->id,
            "role" => $this->role,
            "status" => $this->status,
            "department" => $this->department,
            "activated_at" => $this->activated_at?->toDateTimeString(),
            "deactivated_at" => $this->deactivated_at?->toDateTimeString(),
            "user" => $this->whenLoaded("user", fn () => [
                "id" => $this->user?->id,
                "name" => trim(($this->user?->first_name ?? "") . " " . ($this->user?->last_name ?? "")) ?: null,
                "email" => $this->user?->email,
                "avatar" => $this->user?->avatar,
            ]),
        ];
    }
}
