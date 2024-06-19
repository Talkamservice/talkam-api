<?php

namespace App\Services\User;

use App\Models\User;
use App\Services\Media\FileService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AvatarService
{
    public ?User $user;
    public array $files = [];

    function __construct()
    {
        $this->user = auth()->user();
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
}
