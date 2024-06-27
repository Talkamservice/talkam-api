<?php

namespace App\Services\User;

use App\Constants\General\StatusConstants;
use App\Constants\Media\FileConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\Avatar;
use App\Models\User;
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


    function setUser(User $user)
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

            $this->user->update([
                "avatar" => $data["avatar"]
            ]);

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
            "avatar" => "nullable|image|" . Rule::requiredIf(empty($id)),
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

        if (!empty($image = $data["avatar"] ?? null)) {
            $data["image_id"] = $this->file_service->saveFromFile($image, FileConstants::AVATAR_PATH, null, auth()->id())->id;
            unset($data["avatar"]);
        }

        return Avatar::create($data);
    }

    public function delete($avatar_id)
    {
        $avatar = self::getById($avatar_id);
        $avatar->delete();
        $this->file_service->cleanDelete($avatar->image_id);
    }

    public function list()
    {
        return Avatar::latest();
    }
}
