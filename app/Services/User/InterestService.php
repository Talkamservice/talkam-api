<?php

namespace App\Services\User;

use App\Models\User;
use App\Models\UserInterest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InterestService
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
            "user_id" => "nullable|exists:users,id|" . Rule::requiredIf(empty($this->user)),
            "category_id" => "required|exists:post_categories,id",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }


    public function save(array $data)
    {
        DB::beginTransaction();
        try {
            $data = self::validate($data);

            if (empty($this->user)) {
                $this->user = User::find($data["user_id"]);
            }

            UserInterest::firstOrCreate([
                "user_id" => $this->user->id,
                "category_id" => $data["category_id"],
            ]);

            DB::commit();
            return $this->user->refresh();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function addRemove(array $data)
    {
        $validator = Validator::make($data, [
            "user_id" => "nullable|exists:users,id|" . Rule::requiredIf(empty($this->user)),
            "category_id" => "required|exists:post_categories,id",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data = $validator->validated();

        if (empty($this->user)) {
            $this->user = User::find($data["user_id"]);
        }

        $category_id  = $data["category_id"];
        $user_id = $this->user->id;

        if (self::isInterestPresent($category_id, $user_id)) {
            self::removeInterest($category_id, $user_id);
        } else {
            self::addInterest($category_id, $user_id);
        }

        return self::isInterestPresent($category_id, $user_id);
    }

    public static function isInterestPresent($category_id, $user_id)
    {
        return UserInterest::where([
            "category_id" => $category_id,
            "user_id" => $user_id,
        ])->exists();
    }

    public static function removeInterest($category_id, $user_id)
    {
        UserInterest::where([
            "category_id" => $category_id,
            "user_id" => $user_id,
        ])->delete();
    }

    public static function addInterest($category_id, $user_id)
    {
        UserInterest::create([
            "category_id" => $category_id,
            "user_id" => $user_id,
        ]);
    }
}
