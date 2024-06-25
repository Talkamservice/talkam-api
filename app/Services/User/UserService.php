<?php

namespace App\Services\User;

use App\Constants\Account\User\UserConstants;
use App\Constants\General\AppConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\MethodsHelper;
use App\Models\AccountDeactivation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserService
{
    public User $user;
    public $interest_service;

    public function __construct()
    {
        $this->interest_service = new InterestService;
    }

    public static function init(): self
    {
        return app()->make(self::class);
    }

    public static function getById($id): User
    {
        $model = User::where("id", $id)->first();
        if (empty($model)) {
            throw new ModelNotFoundException("User not found");
        }
        return $model;
    }


    public function validate(array $data, $id = null): array
    {
        $validator = Validator::make($data, [
            'fcm_token' => 'nullable|string',
            "avatar" => "nullable|numeric",
            "first_name" => "nullable|string",
            "middle_name" => "nullable|string",
            "last_name" => "nullable|string",
            "role" => "nullable|" . Rule::in(UserConstants::ROLES),
            "email" => "required|email|unique:users,email,$id|" . Rule::requiredIf(empty($id)),
            "username" => "nullable|string|unique:users,username,$id",
            "status" => "nullable|string",
            'password' => [Rule::requiredIf(empty($id))],
            "phone_number" => "nullable",
            "gender" => Rule::in(AppConstants::GENDERS) . "|nullable",
        ], [
            'email.unique' => "The email address has already been used by another user",
            'username.unique' => "The email address has already been used by another user",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }


    public function create(array $data): User
    {
        $data = self::validate($data);

        $data = array_merge([
            'status' => StatusConstants::ACTIVE,
            'role' => $data["role"] ?? UserConstants::USER
        ], $data);

        $data['password'] = !empty($data['password'] ?? null) ? Hash::make($data['password']) : null;
        $user = User::create($data);

        if (!empty($avatar = $data["avatar"] ?? null)) {
            (new AvatarService)->setUser($user)->update([
                "avatar" => $avatar
            ]);
        }

        return $user;
    }

    private static function generateUsername()
    {
        $username = MethodsHelper::getRandomToken(10);
        $username = ucfirst(strtolower($username));

        $check = User::where("username", $username)->count();

        if ($check > 0) {
            return self::generateUsername();
        }

        return $username;
    }

    public function update(array $data, $id = null)
    {
        $validator = Validator::make($data, [
            "name" => "required|string",
            "avatar" => "nullable|string",
            "interests" => "nullable|array",
            "interests.*" => "required|exists:post_categories,id",
            "username" => "nullable|unique:users,username,$id",
            "age" => "nullable|numeric",
        ], [
            "username.unique" => "The username has already been taken"
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data = $validator->validated();


        $names = self::getNames($data["name"]);
        $user = !empty($id) ? $this->getById($id) : auth()->user();

        if (isset($data["interests"])) {
            $interests = $data["interests"];
            foreach ($interests ?? [] as $key => $category_id) {
                $this->interest_service->save([
                    "user_id" => $user->id,
                    "category_id" => $category_id
                ]);
            }

            unset($data["interests"]);
        }

        $user->update(array_merge($data, $names));
        return $user->refresh();
    }

    public function eraseData()
    {
        DB::beginTransaction();
        try {
            $user = auth()->user();
            $this->clearUserData($user);
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function deleteAccount(array $data)
    {
        DB::beginTransaction();
        try {
            $user = auth()->user();
            $this->clearUserData($user);

            AccountDeactivation::create([
                "user_id" => $user->id,
                "email" => $user->email,
                "reason" => $data["reason"] ?? null,
                "status" => StatusConstants::CONFIRMED
            ]);

            $user->forceDelete();
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function delete($id)
    {
        DB::beginTransaction();
        try {
            $user = $this->getById($id);
            $this->clearUserData($user);
            $user->forceDelete();
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function clearUserData($user)
    {
        optional($user->notifications())->delete();
    }

    public function suspend($status, $id)
    {
        if (!in_array($status, [StatusConstants::ACTIVE, StatusConstants::INACTIVE])) {
            throw new InvalidRequestException("Invalid status provided");
        }

        $user = $this->getById($id);
        $user->update([
            "status" => $status
        ]);
        return $user;
    }

    public static function getNames($fullName)
    {
        // Split the full name into an array of words
        $nameParts = explode(' ', $fullName);

        // Extract first name, middle name (if present), and last name
        $firstName = array_shift($nameParts);
        $lastName = array_pop($nameParts);
        $middleName = implode(' ', $nameParts);

        // Create an array based on the presence of the middle name
        $result = [
            'first_name' => $firstName,
            'last_name' => $lastName,
        ];

        if (!empty($middleName)) {
            $result['middle_name'] = $middleName;
        }

        return $result;
    }
}
