<?php

namespace App\Http\Controllers\Api\V2\User;

use App\Constants\General\ApiConstants;
use App\Constants\General\AppConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Users\UserResource;
use App\Services\User\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Exception;

class ProfileController extends Controller
{
    /**
     * v2 edit profile: bio capped at 300, phone editable, email immutable
     * (simply never accepted). Name split via UserService::getNames.
     */
    public function update(Request $request)
    {
        try {
            $user = auth()->user();

            $validator = Validator::make($request->all(), [
                "name" => "nullable|string|max:150",
                "bio" => "nullable|string|max:300",
                "phone_number" => "nullable|string|max:30",
                "avatar" => "nullable|string",
                "username" => [
                    'nullable',
                    'unique:users,username,' . $user->id,
                    'regex:/^[\w-]*$/',
                ],
                "gender" => Rule::in(AppConstants::GENDERS) . "|nullable",
                "date_of_birth" => 'nullable|date_format:Y-m-d|before:today',
                "password" => "nullable|string|confirmed",
            ], [
                "username.unique" => "The username has already been taken",
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            // Email is intentionally absent from the rules and stripped
            // here — immutable in v2.
            $data = collect($validator->validated())->filter(fn ($v) => $v !== null)->all();

            if (isset($data["name"])) {
                $data = array_merge($data, UserService::getNames($data["name"]));
                unset($data["name"]);
            }

            if (isset($data["password"])) {
                $data["password"] = Hash::make($data["password"]);
            }

            $user->update($data);

            return ApiHelper::validResponse("Profile updated successfully", UserResource::make($user->refresh()));
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
