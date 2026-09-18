<?php

namespace App\Http\Controllers\Api\V2\Auth;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\User\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Exception;

class UsernameController extends Controller
{
    public function available(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "username" => [
                    'required',
                    'string',
                    'regex:/^[\w-]*$/',
                ],
            ], [
                'username.regex' => "The username can only contain letters, numbers, underscores, and dashes, and no spaces",
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $username = $validator->validated()["username"];
            $available = empty(UserService::getByUsername($username));

            return ApiHelper::validResponse("Username availability checked successfully", [
                "username" => $username,
                "available" => $available,
            ]);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
