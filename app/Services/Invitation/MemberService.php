<?php

namespace App\Services\Invitation;

use App\Constants\Invitation\InvitationConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Models\Admin;
use App\Models\Invitation;
use App\Services\User\UserService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class MemberService
{
    public static  function findOrCreate($user_id, array $data = null)
    {
        $invite = Invitation::find($data["invitation_id"]);
        $source = $invite->source;

        if ($source == InvitationConstants::SUPER_ADMIN) {
            $member = Admin::create(array_merge([
                "user_id" => $user_id,
            ], $data));
        }

        return $member ?? null;
    }

    public static function removeMember($member_id, $source, $confirmation = "no")
    {
        $member = self::getById($member_id, $source);

        // if ($member->role == UserConstants::ADMIN) {
        //     throw new InvalidRequestException("Access denied.");
        // }

        $user = $member->user;

        if ($user?->hasRole([$member->role])) {
            $user?->removeRole($member->role);
        }

        $member->delete();
        InvitationService::delete($member->invitation_id);

        if ($confirmation == "yes") {
            (new UserService)->delete($member->user_id);
        }
    }

    /**
     * Change workspace member role.
     *
     * @param  array $data
     * @return void
     */
    public static function changeRole(array $data)
    {
        $validator = Validator::make($data, [
            "member_id" => "required|numeric",
            "role_id" => "required|exists:roles,id",
            "source" => "required|string|" . Rule::in(array_keys(InvitationConstants::SOURCES))
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data = $validator->validated();

        $member = self::getById($data["member_id"], $data["source"]);

        optional($member->user)->removeRole($member->role);
        $role = Role::findById($data["role_id"]);

        // if ($member->role == UserConstants::OWNER) {
        //     throw new InvalidRequestException("Access denied.");
        // }

        $member->update([
            "role" => $role->name
        ]);

        optional($member->user)->assignRole($role->name);
    }

    public static function getModelBySource(string $source)
    {
        if ($source == InvitationConstants::SUPER_ADMIN) {
            $model = Admin::class;
        }

        return $model ?? null;
    }

    public static function getById($id, string $source)
    {
        $model = self::getModelBySource($source);

        if (empty($model)) {
            throw new InvalidRequestException("Invalid source provided");
        }

        $member = $model::where("id", $id)->first();

        if (empty($member)) {
            throw new InvalidRequestException("Member not found");
        }

        return $member;
    }
}
