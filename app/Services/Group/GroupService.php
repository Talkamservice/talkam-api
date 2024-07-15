<?php

namespace App\Services\Group;

use App\Constants\Account\User\UserConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\MethodsHelper;
use App\Models\Group;
use App\Models\GroupMember;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class GroupService
{
    public static function getById($key, $column = "id"): Group
    {
        $group = Group::where($column, $key)->first();
        if (empty($group)) {
            throw new ModelNotFoundException("Group not found");
        }
        return $group;
    }

    public static function getExecutiveById($key, $column = "id")
    {
        $group_executive = GroupMember::where($column, $key)->first();
        if (empty($group_executive)) {
            throw new ModelNotFoundException("Group not found");
        }
        return $group_executive;
    }


    public static function getGroupAdmins($group_id)
    {
        $group = self::getById($group_id);
        $group_admins = GroupMember::where([
            "group_id" => $group->id,
            "role" => UserConstants::ADMIN
        ])->get();

        return $group_admins;
    }

    public static function validate(array $data, $id = null)
    {
        $validator = Validator::make($data, [
            "category_id" => "required|exists:post_categories,id",
            "name" => "required|string",
            "description" => "nullable|string",
            "status" => "nullable|string",
            "rules" => "nullable|string",
            "image" => "nullable|string",
            "tags" => "nullable|array",
            "tags.*" => "string",
            "can_post" => "nullable|numeric",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }


    public function create(array $data)
    {
        DB::beginTransaction();
        try {
            $data = $this->validate($data);

            $data["created_by"] = auth()->id();
            $data["uuid"] = self::generateUniqueId();

            $group = Group::create($data);

            (new GroupMemberService)->create([
                "group_id" => $group->id,
                "user_id" => auth()->id(),
                "role" => UserConstants::OWNER,
                "status" => StatusConstants::ACTIVE
            ]);

            $this->notify($group);

            DB::commit();
            return $group;
        } catch (Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function update(array $data, $id)
    {
        DB::beginTransaction();
        try {
            $data = $this->validate($data, $id);

            $group = self::getById($id);
            $group->update($data);

            DB::commit();
            return $group->refresh();
        } catch (Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }
    public function notify($group)
    {
    }

    public function generateUniqueId($length = 6)
    {
        $uuid = "G-" . MethodsHelper::generateRandomDigits($length);
        $check = Group::where("uuid", $uuid)->count();
        if ($check > 0) {
            return self::generateUniqueId($length);
        }
        return $uuid;
    }

    public static function list(array $data = [])
    {
        $builder = Group::with("creator");

        if (!empty($key = $data["search"] ?? null)) {
            $builder = $builder->search($key);
        }

        if (!empty($key = $data["category_id"] ?? null)) {
            $builder = $builder->where("category_id", $key);
        }

        if (!empty($key = $data["tab"] ?? null)) {
            if ($key == "latest") {
                $builder = $builder->latest();
            }
        }

        return $builder;
    }
}
