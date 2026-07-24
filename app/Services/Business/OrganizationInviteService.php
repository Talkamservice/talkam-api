<?php

namespace App\Services\Business;

use App\Constants\Account\User\UserConstants;
use App\Constants\Auth\PinConstants;
use App\Constants\Business\OrganizationConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\Invitation\InvitationService;
use App\Services\Notifications\Business\OrganizationInviteNotificationService;
use App\Services\User\UserService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Org invites ride the shared `invitations` table via the additive
 * organization_id column — the same shape §05 used for group invites. Every
 * read here filters whereNotNull('organization_id') AND on the caller's own
 * organization, so v1 admin invites and group invites are invisible to this
 * lane and vice versa.
 */
class OrganizationInviteService
{
    public UserService $user_service;

    public function __construct()
    {
        $this->user_service = new UserService;
    }

    /* ── Sending ────────────────────────────────────────────────────────── */

    public function invite(Organization $organization, User $inviter, array $data): array
    {
        $validator = Validator::make($data, [
            "invites" => "required|array|min:1|max:" . config("business.invitations.max_per_request"),
            "invites.*.email" => "required|email|max:190",
            "invites.*.role" => ["required", "string", Rule::in(OrganizationConstants::INVITABLE_ROLES)],
            "invites.*.department" => "nullable|string|max:100",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $invites = $validator->validated()["invites"];

        // De-duplicate within the request itself before any seat maths.
        $seen = [];
        foreach ($invites as $row) {
            $email = strtolower($row["email"]);
            if (isset($seen[$email])) {
                throw new InvalidRequestException("{$email} appears more than once in this batch.");
            }
            $seen[$email] = true;
        }

        $this->assertSeatsAvailable($organization, count($invites));

        foreach (array_keys($seen) as $email) {
            $this->assertNotAlreadyInvited($organization, $email);
        }

        $expiry_days = (int) config("business.invitations.expiry_days");
        $created = [];

        DB::beginTransaction();
        try {
            foreach ($invites as $row) {
                $email = strtolower($row["email"]);

                $created[] = Invitation::create([
                    "uuid" => InvitationService::generateUuid(),
                    "invited_by" => $inviter->id,
                    "organization_id" => $organization->id,
                    "invitee_email" => $email,
                    "invite_role" => $row["role"],
                    "department" => $row["department"] ?? null,
                    "user_id" => User::where("email", $email)->first()?->id,
                    "invite_expires_at" => now()->addDays($expiry_days),
                    "source" => OrganizationConstants::INVITE_SOURCE,
                    "status" => StatusConstants::PENDING,
                ]);
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }

        foreach ($created as $invitation) {
            $this->notify($invitation);
        }

        return $created;
    }

    private function assertSeatsAvailable(Organization $organization, int $adding): void
    {
        $licensed = (int) $organization->seats_licensed;
        $used = $organization->seatsUsed();
        $remaining = $licensed - $used;

        if ($adding > $remaining) {
            throw new InvalidRequestException(
                "That would exceed your licensed seats — {$remaining} of {$licensed} remaining. Increase your seat count from Billing first."
            );
        }
    }

    private function assertNotAlreadyInvited(Organization $organization, string $email): void
    {
        $pending = Invitation::where([
            "organization_id" => $organization->id,
            "invitee_email" => $email,
            "status" => StatusConstants::PENDING,
        ])->exists();

        if ($pending) {
            throw new InvalidRequestException("{$email} already has a pending invite to this organization.");
        }

        $member = OrganizationMember::where("organization_id", $organization->id)
            ->whereHas("user", fn ($q) => $q->where("email", $email))
            ->exists();

        if ($member) {
            throw new InvalidRequestException("{$email} is already a member of this organization.");
        }
    }

    /**
     * Invite email. Delivery is wrapped so a mail-transport failure never
     * loses the invitation row — the admin can resend from the roster.
     */
    private function notify(Invitation $invitation): void
    {
        try {
            OrganizationInviteNotificationService::send($invitation);
        } catch (\Throwable $th) {
            logger("Organization invite mail failed for invitation {$invitation->id}: " . $th->getMessage());
        }
    }

    /* ── Roster ─────────────────────────────────────────────────────────── */

    public static function roster(Organization $organization, array $filters = [])
    {
        $builder = Invitation::where("organization_id", $organization->id)
            ->with("inviter:id,first_name,last_name,email")
            ->latest();

        if (!empty($role = $filters["role"] ?? null)) {
            $builder->where("invite_role", $role);
        }

        if (!empty($status = $filters["status"] ?? null)) {
            $builder->where("status", $status);
        }

        if (!empty($search = $filters["search"] ?? null)) {
            $builder->where("invitee_email", "like", "%{$search}%");
        }

        return $builder;
    }

    /** Tenant-scoped fetch — an id from another org is a not-found here. */
    public static function scopedById(Organization $organization, $id): Invitation
    {
        $invitation = Invitation::where("organization_id", $organization->id)
            ->where("id", $id)
            ->first();

        if (empty($invitation)) {
            throw new ModelNotFoundException("Invite not found");
        }

        return $invitation;
    }

    public function resend(Invitation $invitation): Invitation
    {
        if ($invitation->status !== StatusConstants::PENDING) {
            throw new InvalidRequestException("Only pending invites can be resent.");
        }

        $invitation->update([
            "invite_expires_at" => now()->addDays((int) config("business.invitations.expiry_days")),
        ]);

        $this->notify($invitation->refresh());

        return $invitation;
    }

    public function revoke(Invitation $invitation): Invitation
    {
        if ($invitation->status !== StatusConstants::PENDING) {
            throw new InvalidRequestException("Only pending invites can be revoked.");
        }

        $invitation->update([
            "status" => StatusConstants::CANCELLED,
            "revoke_at" => now(),
        ]);

        return $invitation->refresh();
    }

    /* ── Recipient side (public) ────────────────────────────────────────── */

    /**
     * Invite-landing payload. Deliberately minimal — this endpoint is
     * unauthenticated, so it exposes the company NAME and the invited role
     * and nothing else: no seat counts, no rosters, no inviter identity.
     */
    public static function landing(string $uuid): array
    {
        $invitation = self::pendingByUuid($uuid);

        if (empty($invitation->opened_at)) {
            $invitation->update(["opened_at" => now()]);
        }

        $organization = $invitation->organization;

        return [
            "organization_name" => $organization?->name,
            "organization_initial" => strtoupper(mb_substr((string) $organization?->name, 0, 1)),
            "email" => $invitation->invitee_email,
            "role" => $invitation->invite_role,
            "department" => $invitation->department,
            "status" => $invitation->status,
            "expires_at" => $invitation->invite_expires_at
                ? Carbon::parse($invitation->invite_expires_at)->toDateTimeString()
                : null,
        ];
    }

    public static function pendingByUuid(string $uuid): Invitation
    {
        $invitation = Invitation::where("uuid", $uuid)
            ->whereNotNull("organization_id")
            ->first();

        if (empty($invitation)) {
            throw new ModelNotFoundException("Invite not found");
        }

        if ($invitation->status !== StatusConstants::PENDING) {
            throw new InvalidRequestException("This invitation has already been used or revoked.");
        }

        if (!empty($ex = $invitation->invite_expires_at) && Carbon::parse($ex)->isPast()) {
            throw new InvalidRequestException("This invitation has expired. Ask your administrator to resend it.");
        }

        return $invitation;
    }

    /**
     * Accept: creates the account (or attaches an existing one) and the active
     * membership. The email is taken from the INVITATION, never from the
     * request — a link holder cannot enrol a different address.
     */
    public function accept(string $uuid, array $data): array
    {
        $validator = Validator::make($data, [
            "full_name" => "required|string|min:2|max:150",
            "password" => [
                "required",
                "string",
                "regex:/" . PinConstants::PASSWORD_REGEX . "/",
            ],
        ], [
            "password.regex" => "The password must be 8-32 characters and contain at least one uppercase letter, one lowercase letter, one number and one special character.",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        $invitation = self::pendingByUuid($uuid);
        $organization = $invitation->organization;

        if (empty($organization)) {
            throw new ModelNotFoundException("Invite not found");
        }

        $existing = User::where("email", $invitation->invitee_email)->first();

        if (!empty($existing) && $existing->organizationMemberships()->exists()) {
            throw new InvalidRequestException("This account already belongs to an organization on TalkAM for Business.");
        }

        DB::beginTransaction();
        try {
            if (empty($existing)) {
                $names = UserService::getNames($validated["full_name"]);

                $user = $this->user_service->create(array_merge($names, [
                    "email" => $invitation->invitee_email,
                    "username" => OrganizationService::generateUsername($invitation->invitee_email),
                    "password" => $validated["password"],
                    "role" => UserConstants::USER,
                ]));

                // The invite link itself proves control of the mailbox.
                $user->update(["email_verified_at" => now()]);
            } else {
                $user = $existing;
            }

            OrganizationMember::updateOrCreate(
                [
                    "organization_id" => $organization->id,
                    "user_id" => $user->id,
                ],
                [
                    "role" => $invitation->invite_role,
                    "status" => OrganizationConstants::MEMBER_ACTIVE,
                    "department" => $invitation->department,
                    "invited_by" => $invitation->invited_by,
                    "activated_at" => now(),
                ]
            );

            $invitation->update([
                "user_id" => $user->id,
                "status" => StatusConstants::ACCEPTED,
                "response" => "accept",
                "response_date" => now(),
            ]);

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }

        return [
            "user" => $user->refresh(),
            "organization" => $organization,
            "role" => $invitation->invite_role,
        ];
    }

    /* ── CSV import ─────────────────────────────────────────────────────── */

    /**
     * Parse a roster CSV into invite rows. Never throws on a bad ROW — bad
     * rows come back flagged so the screen can show them inline; only a bad
     * FILE (wrong type, too large, unreadable) is a 422.
     */
    public function parseCsv(?UploadedFile $file): array
    {
        $validator = Validator::make(["file" => $file], [
            "file" => "required|file|mimes:csv,txt|max:" . config("business.invitations.csv_max_kilobytes"),
        ], [
            "file.mimes" => "Only .csv files are supported",
            "file.max" => "File must be 5MB or smaller",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $handle = fopen($file->getRealPath(), "r");

        if ($handle === false) {
            throw new InvalidRequestException("That file could not be read. Try again.");
        }

        $rows = [];
        $errors = [];
        $header = null;
        $line = 0;

        while (($columns = fgetcsv($handle)) !== false) {
            $line++;

            if ($columns === [null] || $columns === false) {
                continue;
            }

            $columns = array_map(fn ($value) => trim((string) $value), $columns);

            if ($header === null) {
                $header = array_map(fn ($value) => strtolower($value), $columns);
                continue;
            }

            if (count(array_filter($columns, fn ($v) => $v !== "")) === 0) {
                continue;
            }

            $row = [
                "email" => $this->column($header, $columns, "email"),
                "role" => strtolower((string) $this->column($header, $columns, "role")),
                "department" => $this->column($header, $columns, "department"),
            ];

            $row_errors = [];

            if (!filter_var($row["email"], FILTER_VALIDATE_EMAIL)) {
                $row_errors[] = "Not a valid email address";
            }

            if (!in_array($row["role"], OrganizationConstants::INVITABLE_ROLES, true)) {
                $row_errors[] = "Role must be employee or therapist";
            }

            if (empty($row_errors)) {
                $rows[] = $row;
            } else {
                $errors[] = array_merge($row, ["line" => $line, "errors" => $row_errors]);
            }
        }

        fclose($handle);

        if ($header === null) {
            throw new InvalidRequestException(
                "File must be a .csv with columns: email, role, department."
            );
        }

        return [
            "filename" => $file->getClientOriginalName(),
            "rows" => $rows,
            "invalid_rows" => $errors,
            "valid_count" => count($rows),
            "invalid_count" => count($errors),
        ];
    }

    private function column(array $header, array $columns, string $name): ?string
    {
        $index = array_search($name, $header, true);

        if ($index === false || !array_key_exists($index, $columns)) {
            return null;
        }

        return $columns[$index] !== "" ? $columns[$index] : null;
    }
}
