<?php

namespace App\Services\Group;

use App\Constants\Account\User\UserConstants;
use App\Constants\General\StatusConstants;
use App\Models\GroupExecutive;
use App\Models\User;
use App\Services\Group\GroupService;
use App\Services\User\UserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class GroupExecutiveService
{
    protected $group_service;

    public function __construct()
    {
        $this->group_service = new GroupService;
    }
    public static function validate(array $data, $id = null)
    {
        $validator = Validator::make($data, [
            'group_id' => 'nullable|exists:groups,id',
            'user_id' => 'nullable|exists:users,id',
            "role" => "required|string",
            "status" => "required|string",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }


    public static function create(array $data)
    {
        $data = self::validate($data);
        return GroupExecutive::create($data);
    }

    public static function addNewAdmin(array $data)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($data, [
                'group_id' => 'nullable|exists:groups,id',
                'user_id' => 'nullable|exists:users,id',
                'name' => 'required|string',
                "role" => "nullable|string",
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $data = $validator->validated();

            $user = User::where("email", $data["email"])->first();

            $executive = self::create([
                "user_id" => $user->id,
                "group_id" => $data["group_id"],
                "role" => $data["role"] ?? UserConstants::MEMBER,
                "status" => StatusConstants::ACTIVE,
            ]);

            // Notification::send($user, new NewGroupAdminNotification($executive, $password));
            DB::commit();
            return $executive;
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
                'group_id' => 'nullable|exists:groups,id',
                'name' => 'required|string',
                "role" => "nullable|string",
                "status" => "nullable|string"
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $data = $validator->validated();
        $executive = $this->group_service->getExecutiveById($id);

            $names = (new UserService)->getNames($data["name"]);
            $executive->user()->update($names);

            unset($data["name"]);
            $executive->update($data);
            DB::commit();
            return $executive;
        } catch (\Throwable $th) {
            DB::rollback();
            throw $th;
        }
    }
}
