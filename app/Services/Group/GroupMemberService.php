<?php

namespace App\Services\Group;

use App\Constants\Account\User\UserConstants;
use App\Constants\General\StatusConstants;
use App\Events\RefreshNotification;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Http\Resources\Group\GroupMemberResource;
use App\Models\GroupMember;
use App\Models\GroupMemberReport;
use App\Models\User;
use App\Notifications\Group\SuspendGroupMemberNotification;
use App\Services\Group\GroupService;
use App\Services\User\UserService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class GroupMemberService
{
    protected $group_service;

    public function __construct()
    {
        $this->group_service = new GroupService;
    }

    public static function getById($key, $column = "id")
    {
        $group_member = GroupMember::where($column, $key)->first();
        if (empty($group_member)) {
            throw new ModelNotFoundException("Group member not found");
        }
        return $group_member;
    }

    public static function validate(array $data, $id = null)
    {
        $validator = Validator::make($data, [
            'group_id' => 'required|exists:groups,id',
            'user_id' => 'required|exists:users,id',
            "role" => "nullable|string",
            "status" => "nullable|string",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public static function create(array $data)
    {
        $data = self::validate($data);
        $data["role"] = $data["role"] ?? UserConstants::MEMBER;
        return GroupMember::firstOrCreate([
            "user_id" => $data["user_id"],
            "group_id" => $data["group_id"],
        ], $data);
    }

    public static function addNewAdmin(array $data)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($data, [
                'group_id' => 'required|exists:groups,id',
                'user_id' => 'required|exists:users,id',
                "role" => "nullable|string",
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $data = $validator->validated();

            $member = self::create([
                "user_id" => $data["user_id"],
                "group_id" => $data["group_id"],
                "role" => $data["role"] ?? UserConstants::MEMBER,
                "status" => StatusConstants::ACTIVE,
            ]);

            // Notification::send($user, new NewGroupAdminNotification($member, $password));
            DB::commit();
            return $member;
        } catch (\Throwable $th) {
            DB::rollback();
            throw $th;
        }
    }

    public static function removeByUserId(array $data)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($data, [
                'group_id' => 'required|exists:groups,id',
                'user_id' => 'required|exists:users,id',
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $data = $validator->validated();

            $member = GroupMember::where($data)->first();

            if (empty($member)) {
                throw new InvalidRequestException("You are not a member of the group");
            }

            $member->delete();
            DB::commit();
            return $member;
        } catch (\Throwable $th) {
            DB::rollback();
            throw $th;
        }
    }

    public function update(array $data, $id)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($data, [
                "role" => "nullable|string",
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $data = $validator->validated();

            $member = $this->getById($id);
            $member->update($data);
            DB::commit();
            return $member;
        } catch (\Throwable $th) {
            DB::rollback();
            throw $th;
        }
    }

    public static function list($group_id, array $data = [])
    {
        $builder = GroupMember::where("group_id", $group_id);

        if (!empty($key = $data["search"] ?? null)) {
            $builder = $builder->search($key);
        }

        if (!empty($key = $data["role"] ?? null)) {
            $builder = $builder->where("role", $key);
        }

        if (!empty($key = $data["status"] ?? null)) {
            $builder = $builder->where("status", $key);
        }

        return $builder;
    }

    public static function listByGroup($group_id, array $data = [])
    {
        $builder = GroupMember::where("group_id", $group_id);

        $data = array_map(function ($role) use ($builder) {
            $group_members = $builder->clone()->where("role", $role)->with("user")->whereIn("status", [StatusConstants::ACTIVE, StatusConstants::SUSPENDED])->get()->sortByDesc("name");
            return GroupMemberResource::collection($group_members);
        }, UserConstants::GROUP_ROLES);

        return $data;
    }

    public function reportMember(array $data = [])
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($data, [
                "group_member_id" => "required|numeric|exists:group_members,id",
                "reason" => "required|string",
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $data = $validator->validated();

            $group_member = self::getById($data["group_member_id"]);

            $report = GroupMemberReport::create([
                "user_id" => $group_member->user_id,
                "group_member_id" => $group_member->id,
                "reason" => $data["reason"],
            ]);


            DB::commit();
            return $report;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function suspendMember($id)
    {
        DB::beginTransaction();
        try {
            $group_member = self::getById($id);

            if ($group_member->status == StatusConstants::SUSPENDED) {
                $status = StatusConstants::ACTIVE;
            } else {
                $status = StatusConstants::SUSPENDED;
            }

            $group_member->update([
                'status' => $status,
            ]);

            if ($group_member->status == StatusConstants::SUSPENDED) {
                $message = "You have been suspended by {$group_member->group->name} group moderator for failing to comply with " . config('app.name') . " rules and guidelines.";
            } else {
                $message = "You have been unsuspended by {$group_member->group->name} group moderator. Please ensure that you comply with the rules and guidelines of " . config('app.name') . " moving forward to avoid any further actions.";
            }
            Notification::send($group_member->user, new SuspendGroupMemberNotification($group_member, $message));
            broadcast(new RefreshNotification($group_member->user_id));
            DB::commit();
            return $group_member;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
