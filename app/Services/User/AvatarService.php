<?php

namespace App\Services\User;

use App\Constants\ActivityLog\ActivitiesConstants;
use App\Constants\ActivityLog\ActivityLogConstants;
use App\Constants\General\StatusConstants;
use App\Constants\Media\FileConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\Avatar;
use App\Models\User;
use App\Services\ActivityLog\ActivityLogService;
use App\Services\Media\FileService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AvatarService
{
    public ?User $user;
    public $file_service;
    public array $files = [];

    function __construct()
    {
        $this->user = auth()->user();
        $this->file_service = new FileService;
    }

    public static function getById($id)
    {
        $avatar = Avatar::where("id", $id)->first();
        if (empty($avatar)) {
            throw new ModelNotFoundException("Avatar not found");
        }
        return $avatar;
    }


    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    public function validate(array $data): array
    {
        $validator = Validator::make($data, [
            "user_id" => "nullable|numeric|exists:users,id|" . Rule::requiredIf(empty($this->user)),
            "avatar" => "required|string"
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }


    public function update(array $data)
    {
        DB::beginTransaction();
        try {
            $data = self::validate($data);

            if (empty($this->user)) {
                $this->user = User::find($data["user_id"]);
            }
            $old_avatar = $this->user->avatar();

            $this->user->update([
                "avatar" => $data["avatar"]
            ]);
            (new ActivityLogService)
                ->setEvent("updated")
                ->setTitle("Updated An Avatar")
                ->setDescription((auth()->user()?->email) . " updated " . $this->user . "'s avatar")
                ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
                ->setActivity(ActivitiesConstants::UPDATED_AVATAR)
                ->setModel(Avatar::class, $this->user->avatar->id)
                ->setAdmin(auth()->user()?->id)
                ->setData(
                    [
                        "Avatar" => $this->user->avatar->refresh()->toArray()
                    ],
                    [
                        "Old Avatar" => $old_avatar->toArray()
                    ]
                )
                ->setUrl(request()->fullUrl())
                ->log();
            DB::commit();
            return $this->user->refresh();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }


    public static function validateCrud(array $data, $id = null)
    {
        $validator = Validator::make($data, [
            "name" => 'required|string',
            "description" => 'nullable|string',
            "image" => "nullable|image|" . Rule::requiredIf(empty($id)),
            "status" => 'required|string|' . Rule::in(StatusConstants::ACTIVE_OPTIONS),
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }


    public function create(array $data)
    {
        $data = self::validateCrud($data);

        if (!empty($image = $data["image"] ?? null)) {
            $data["image"] = $this->file_service->saveFromFileIntoStorage($image, FileConstants::AVATAR_PATH, null, auth()->id());
        }

        $avatar =  Avatar::create($data);

        (new ActivityLogService)
            ->setEvent("created")
            ->setTitle("Created An Avatar")
            ->setDescription((auth()->user()?->email) . " created an avatar")
            ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
            ->setActivity(ActivitiesConstants::CREATED_AVATAR)
            ->setModel(Avatar::class, $avatar->id)
            ->setAdmin(auth()->user()?->id)
            ->setData(
                [
                    "Avatar" => $avatar->refresh()->toArray()
                ]
            )
            ->setUrl(request()->fullUrl())
            ->log();

        return $avatar;
    }

    public function updateCrud(array $data, $id)
    {
        $data = self::validateCrud($data, $id);

        if (!empty($image = $data["image"] ?? null)) {
            $data["image"] = $this->file_service->saveFromFileIntoStorage($image, FileConstants::AVATAR_PATH, null, auth()->id());
        }

        $avatar = self::getById($id);
        $old_avatar = $avatar;
        $avatar->update($data);

        (new ActivityLogService)
            ->setEvent("updated")
            ->setTitle("Updated An Avatar")
            ->setDescription((auth()->user()?->email) . " updated an avatar")
            ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
            ->setActivity(ActivitiesConstants::UPDATED_AVATAR)
            ->setModel(Avatar::class, $avatar->id)
            ->setAdmin(auth()->user()?->id)
            ->setData(
                [
                    "Avatar" => $avatar->refresh()->toArray()
                ],
                [
                    "Old Avatar" => $old_avatar->toArray()
                ]
            )
            ->setUrl(request()->fullUrl())
            ->log();

        return $avatar;
    }

    public function delete($avatar_id)
    {
        $avatar = self::getById($avatar_id);
        $old_avatar =  $avatar;
        $avatar->delete();
        $this->file_service->cleanDelete($avatar->image_id);

        (new ActivityLogService)
            ->setEvent("deleted")
            ->setTitle("Deleted An Avatar")
            ->setDescription((auth()->user()?->email) . " deleted an avatar")
            ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
            ->setActivity(ActivitiesConstants::DELETED_AVATAR)
            ->setModel(Avatar::class, $avatar->id)
            ->setAdmin(auth()->user()?->id)
            ->setData(
                [
                    "Old Avatar" => $old_avatar->toArray()
                ]
            )
            ->setUrl(request()->fullUrl())
            ->log();
    }

    public function list()
    {
        return Avatar::latest();
    }
}
