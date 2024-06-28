<?php

namespace App\Services\Invitation;

use App\Constants\Account\User\UserConstants;
use App\Constants\General\StatusConstants;
use App\Constants\Invitation\InvitationConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\MethodsHelper;
use App\Models\Invitation;
use App\Models\User;
use App\Services\Notifications\Invitation\InvitationNotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class InvitationService
{
    public static function getById($key, $column = "id"): Invitation
    {
        $parish = Invitation::where($column, $key)->first();
        if (empty($parish)) {
            throw new ModelNotFoundException("Invite not found");
        }
        return $parish;
    }

    public static function validate(array $data, $id = null)
    {
        $validator = Validator::make($data, [
            "user_id" => "nullable|exists:users,id",
            "invited_by" => "required|exists:users,id",
            "invitee_email" => "required|email",
            "role_id" => "required|exists:roles,id",
            "invite_expires_at" => "nullable|",
            "response_date" => "nullable|string",
            "response" => "nullable|string",
            "revoke_at" => "nullable|string",
            "notify_on_join" => "nullable|in:0,1",
            "source" => "required|string|" . Rule::in(array_keys(InvitationConstants::SOURCES)),
            "status" => "required|string|" . Rule::in(UserConstants::STATUSES),
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public static function generateUuid()
    {
        $code = "IV_" . MethodsHelper::getRandomToken(10);
        if (Invitation::where("uuid", $code)->count() > 0) {
            return self::generateUuid();
        }
        return $code;
    }

    public static function create(array $data)
    {
        $data = self::validate($data);
        $data["uuid"] = self::generateUuid();
        return Invitation::create($data);
    }


    public static function invite(array $data, array $emails)
    {

        foreach ($emails as $email) {
            
            if (Invitation::where([
                "invitee_email" => $email,
                "status" => StatusConstants::PENDING,
            ])->exists()) {
                throw new InvalidRequestException("This email:{$email} already has a pending invite");
            }

            $invite = self::create([
                "user_id" => $data["user_id"] ?? null,
                "invited_by" => $data["invited_by"],
                "invitee_email" => $email,
                "invited_at" => now(),
                "role_id" => $data["role_id"],
                "invite_expires_at" => $data["invite_expires_at"] ?? null,
                "revoke_at" => $data["revoke_at"] ?? null,
                "notify_on_join" => $data["notify_on_join"] ?? 0,
                "source" => $data["source"] ?? null,
                "status" => $data["status"] ?? StatusConstants::PENDING,
            ]);

            InvitationNotificationService::send($invite);
        }

        return $invite;
    }

    public static function respondToInvite($invite_id, $response)
    {
        DB::beginTransaction();
        try {
            $accepted = $response == "accept";

            $invite = Invitation::find($invite_id);
            if ($invite->status != StatusConstants::PENDING) {
                throw new InvalidRequestException("This invitation has been responded to.");
            }

            if (!empty($exp = $invite->invite_expires_at) && Carbon::parse($exp)->isPast()) {
                throw new InvalidRequestException("This invitation has expired.");
            }

            $data = [
                "response_date" => now(),
                "response" => $response,
                "status" => $accepted ? StatusConstants::ACTIVE : StatusConstants::DECLINED
            ];

            $invitee_email = $invite->invitee_email;
            $invited_user = User::where("email", $invitee_email)->first();

            // If there is no user with the invited email , create a new user
            if (empty($invited_user) && $data["status"] != StatusConstants::DECLINED) {
                throw new InvalidRequestException("No account found for this email", 420);
            }

            if ($accepted) {

                $role = $invite->role->name;

                $member = MemberService::findOrCreate(
                    $invited_user->id,
                    [
                        "invitation_id" => $invite->id,
                        "role" => $role,
                        "status" => StatusConstants::ACTIVE
                    ]
                );

                AccessService::grant($member, $invite);
            }


            $invite->update($data);
            DB::commit();
            return [
                "invite" => $invite->refresh(),
                "user" => $invited_user,
            ];
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }


    public static function delete(int $invitation_id)
    {
        $invitation = self::getById($invitation_id);
        $invitation->delete();
    }
}
