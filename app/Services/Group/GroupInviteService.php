<?php

namespace App\Services\Group;

use App\Constants\Account\User\UserConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Models\GroupMember;
use App\Models\Invitation;
use App\Models\User;
use App\Services\Invitation\InvitationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class GroupInviteService
{
    /**
     * Group invite (person+ icon) — rides the existing invitations table via
     * the additive group_id column. v1's app-level invite flow never sets it.
     */
    public function invite(User $inviter, $group_id, array $data): Invitation
    {
        $group = GroupService::getById($group_id, is_numeric($group_id) ? 'id' : 'uuid');

        $validator = Validator::make($data, [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $email = strtolower($validator->validated()['email']);

        $pending = Invitation::where([
            'invitee_email' => $email,
            'group_id' => $group->id,
            'status' => StatusConstants::PENDING,
        ])->exists();

        if ($pending) {
            throw new InvalidRequestException("This email already has a pending invite to this group.");
        }

        return Invitation::create([
            'uuid' => InvitationService::generateUuid(),
            'invited_by' => $inviter->id,
            'group_id' => $group->id,
            'invitee_email' => $email,
            'user_id' => User::where('email', $email)->first()?->id,
            'source' => 'group',
            'status' => StatusConstants::PENDING,
        ]);
    }

    /**
     * Accepting a group invite = Join semantics (creates membership).
     */
    public function accept(User $user, array $data): GroupMember
    {
        $validator = Validator::make($data, [
            'uuid' => 'required|string',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        DB::beginTransaction();
        try {
            $invite = Invitation::where('uuid', $data['uuid'])
                ->whereNotNull('group_id')
                ->first();

            if (empty($invite) || $invite->status != StatusConstants::PENDING) {
                throw new InvalidRequestException("This invitation is invalid or has already been responded to.");
            }

            if (strtolower($invite->invitee_email) != strtolower($user->email)) {
                throw new InvalidRequestException("This invitation was sent to a different email address.");
            }

            $invite->update([
                'user_id' => $user->id,
                'response' => 'accept',
                'response_date' => now(),
                'status' => StatusConstants::ACTIVE,
            ]);

            $member = GroupMemberService::create([
                'group_id' => $invite->group_id,
                'user_id' => $user->id,
                'role' => UserConstants::MEMBER,
                'status' => StatusConstants::ACTIVE,
            ]);

            DB::commit();
            return $member;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }
}
