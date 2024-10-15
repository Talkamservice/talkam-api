<?php

namespace App\Services\User;

use App\Constants\Account\User\UserConstants;
use App\Constants\ActivityLog\ActivitiesConstants;
use App\Constants\ActivityLog\ActivityLogConstants;
use App\Constants\General\AppConstants;
use App\Constants\General\StatusConstants;
use App\Events\RefreshNotification;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\MethodsHelper;
use App\Models\AccountDeactivation;
use App\Models\User;
use App\Notifications\User\BannedUserNotification;
use App\Notifications\User\PostRestorationNotification;
use App\Notifications\User\PostsRemovedFromApplicationNotification;
use App\Notifications\User\PostSuspensionNotification;
use App\Notifications\User\StrikeUserNotification;
use App\Notifications\User\SuspendUserNotification;
use App\Services\ActivityLog\ActivityLogService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

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

    public static function getById($key, $column = "id"): User
    {
        $model = User::where($column, $key)->first();
        if (empty($model)) {
            throw new ModelNotFoundException("User not found");
        }
        return $model;
    }

    public static function getByUsername($username)
    {
        $model = User::where("username", $username)->first();
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
            "username" => [
                'nullable',
                'unique:users,username,' . $id,
                'regex:/^[\w-]*$/', // Alphanumeric characters, underscores, and dashes allowed
            ],
            "status" => "nullable|string",
            'password' => [Rule::requiredIf(empty($id))],
            "phone_number" => "nullable",
            "gender" => Rule::in(AppConstants::GENDERS) . "|nullable",
            "date_of_birth" => 'nullable|date_format:Y-m-d|before:today',
        ], [
            'email.unique' => "The email address has already been used by another user",
            'username.unique' => "The email address has already been used by another user",
            "username.regex" => "The username can only contain letters, numbers, underscores, and dashes, and no spaces",
            'date_of_birth.date_format' => 'The date of birth must be in the format dd/mm/yyyy',
            'date_of_birth.before' => 'The date of birth must be a date before today',
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

        // Convert date_of_birth to Y-m-d format
        if (isset($data['date_of_birth'])) {
            // Assuming the input is in 'Y-d-m' format, convert it to 'Y-m-d'
            $data['date_of_birth'] = Carbon::createFromFormat('Y-d-m', $data['date_of_birth'])->format('Y-m-d');
        }

        if (!empty($avatar = $data["avatar"] ?? null)) {
            (new AvatarService)->setUser($user)->update([
                "avatar" => $avatar
            ]);
        }
        (new ActivityLogService)
            ->setEvent("created")
            ->setTitle("User Account Created")
            ->setDescription(($user?->email) . " created a user account")
            ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
            ->setActivity(ActivitiesConstants::CREATED_USER_ACCOUNT)
            ->setModel(User::class, $user->id)
            ->setAdmin(auth()->user()?->id)
            ->setData([
                "User Data" => $user->refresh()->toArray(),
            ])
            ->setUrl(request()->fullUrl())
            ->log();
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
        DB::beginTransaction();
        try {
            $validator = Validator::make($data, [
                "name" => "nullable|string",
                "avatar" => "nullable|string",
                "interests" => "nullable|array",
                "interests.*" => "required|exists:post_categories,id",
                "username" => [
                    'nullable',
                    'unique:users,username,' . $id,
                    'regex:/^[\w-]*$/', // Alphanumeric characters, underscores, and dashes allowed
                ],
                "age" => "nullable|numeric",
                "password" => "nullable|string|confirmed",
                "state_id" => "nullable|exists:states,id",
                "country_id" => "nullable|exists:countries,id",
                "gender" => Rule::in(AppConstants::GENDERS) . "|nullable",
                "date_of_birth" => 'nullable|date_format:Y-m-d|before:today',
            ], [
                "username.unique" => "The username has already been taken",
                "username.regex" => "The username can only contain letters, numbers, underscores, and dashes, and no spaces",
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $data = $validator->validated();
            // Capture old user data before update
            $user = !empty($id) ? $this->getById($id) : auth()->user();
            $oldUserData = $user;

            $names = isset($data["name"]) ? self::getNames($data["name"]) : [];
            $user = !empty($id) ? $this->getById($id) : auth()->user();

            if (isset($data["password"])) {
                $data["password"] = Hash::make($data["password"]);
            }

            // Convert date_of_birth to Y-m-d format
            if (isset($data['date_of_birth'])) {
                // Assuming the input is in 'Y-d-m' format, convert it to 'Y-m-d'
                $data['date_of_birth'] = Carbon::createFromFormat('Y-d-m', $data['date_of_birth'])->format('Y-m-d');
            }

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

            (new ActivityLogService)
                ->setEvent("updated")
                ->setTitle("User Account Updated")
                ->setDescription(($user?->email) . " update their account")
                ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
                ->setActivity(ActivitiesConstants::UPDATED_USER_ACCOUNT)
                ->setModel(User::class, $user->id)
                ->setAdmin(auth()->user()?->id)
                ->setData([
                    "Old User Data" => $oldUserData->toArray(),
                ], [
                    "User Data" => $user->refresh()->toArray(),
                ])
                ->setUrl(request()->fullUrl())
                ->log();

            DB::commit();
            return $user->refresh();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function eraseData()
    {
        DB::beginTransaction();
        try {
            $user = auth()->user();
            $erased_user_account = $user;
            $this->clearUserData($user);
            DB::commit();

            // Log the activity
            (new ActivityLogService)
                ->setEvent("data_erased")
                ->setTitle("User Data Erased")
                ->setDescription((auth()->user()?->email) . " erased their data")
                ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
                ->setActivity(ActivitiesConstants::ERASED_USER_DATA)
                ->setModel(User::class, $user->id)
                ->setAdmin(auth()->user()?->id)
                ->setData([
                    "User" => $erased_user_account->refresh()->toArray(),
                ])
                ->setUrl(request()->fullUrl())
                ->log();
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
            $deleted_user_account = $user;
            $this->clearUserData($user);

            AccountDeactivation::create([
                "user_id" => $user->id,
                "email" => $user->email,
                "reason" => $data["reason"] ?? null,
                "status" => StatusConstants::APPROVED
            ]);

            $user->forceDelete();
            DB::commit();

            // Log the activity
            (new ActivityLogService)
                ->setEvent("account_deleted")
                ->setTitle("User Account Deleted")
                ->setDescription((auth()->user()?->email) . " deleted their account")
                ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
                ->setActivity(ActivitiesConstants::DELETED_USER_ACCOUNT)
                ->setModel(User::class, $user->id)
                ->setAdmin(auth()->user()?->id)
                ->setData([
                    "User" => $deleted_user_account->refresh()->toArray(),
                ])
                ->setUrl(request()->fullUrl())
                ->log();
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
            $oldUserData = $user;
            $this->clearUserData($user);
            $user->forceDelete();

            (new ActivityLogService)
                ->setEvent("deleted")
                ->setTitle("User Deleted")
                ->setDescription((auth()->user()?->email) . " deleted a user")
                ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
                ->setActivity(ActivitiesConstants::DELETED_USER)
                ->setModel(User::class, $user->id)
                ->setAdmin(auth()->user()?->id)
                ->setData([
                    "old_user" => $oldUserData->toArray(),
                    "user" => $user->refresh()->toArray(),
                ])
                ->setUrl(request()->fullUrl())
                ->log();
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function clearUserData($user)
    {
        optional($user->notifications())->delete();
        optional($user->pins())->delete();
    }

    public function suspend(Request $request, $status, $id)
    {
        if (!in_array($status, [StatusConstants::ACTIVE, StatusConstants::INACTIVE])) {
            throw new InvalidRequestException("Invalid status provided");
        }
        $user = $this->getById($id);
        $suspension_reason = $request->input('suspend_reason');
        $suspension_duration = $request->input('duration'); // Date input for suspension end
        // Calculate the suspension end date from the input duration
        $suspension_end = Carbon::parse($suspension_duration); // Convert input date to Carbon
        $now = Carbon::now();
        if (!$suspension_end && $suspension_end->lessThanOrEqualTo($now)) {
            return 'Invalid suspension date. The date must be in the future.';
        }
        $days = $now->diffInDays($suspension_end); // Calculate the number of days
        if ($status === StatusConstants::INACTIVE) {
            $user->update([
                'suspend_ban_reason' => $suspension_reason,
                'suspension_end' => $suspension_end,
                "status" => $status
            ]);
            Notification::send($user, new SuspendUserNotification($user, $user->status, $suspension_reason, $suspension_end->toFormattedDateString()));
            broadcast(new RefreshNotification($user->id));
            $user->refresh();
            (new ActivityLogService)
                ->setEvent("suspend")
                ->setTitle("User Suspended")
                ->setDescription((auth()->user()?->email) . " suspended a user")
                ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
                ->setActivity(ActivitiesConstants::SUSPEND_USER)
                ->setModel(User::class, $user->id)
                ->setAdmin(auth()->user()?->id)
                ->setData([
                    "User" => $user->refresh()->toArray(),
                ])
                ->setUrl(request()->fullUrl())
                ->log();
        } else {
            $user->update([
                'suspend_ban_reason' => null,
                'suspension_end' => null,
                "status" => $status
            ]);
            Notification::send($user, new SuspendUserNotification($user, $user->status, null, null));
            broadcast(new RefreshNotification($user->id));
            $user->refresh();
            (new ActivityLogService)
                ->setEvent("unsuspend")
                ->setTitle("Cancelled Suspension")
                ->setDescription((auth()->user()?->email) . " cancelled a user's suspension")
                ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
                ->setActivity(ActivitiesConstants::UNSUSPEND_USER)
                ->setModel(User::class, $user->id)
                ->setAdmin(auth()->user()?->id)
                ->setData([
                    "User" => $user->refresh()->toArray(),
                ])
                ->setUrl(request()->fullUrl())
                ->log();
        }
    }

    public function strike($id)
    {
        $user = $this->getById($id);
        $user->increment("strike");

        Notification::send($user, new StrikeUserNotification($user));
        broadcast(new RefreshNotification($user->id));

        $user->refresh();
        (new ActivityLogService)
            ->setEvent("striked")
            ->setTitle("User Striked")
            ->setDescription((auth()->user()?->email) . " sent a strike to a user")
            ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
            ->setActivity(ActivitiesConstants::STRIKED_USER)
            ->setModel(User::class, $user->id)
            ->setAdmin(auth()->user()?->id)
            ->setData([
                "User" => $user->refresh()->toArray(),
            ])
            ->setUrl(request()->fullUrl())
            ->log();

        return $user;
    }

    public function ban(Request $request, $id)
    {
        // Get the user being banned
        $user = $this->getById($id);
        $ban_reason = $request->input('suspend_ban_reason');

        // Update user status to 'BANNED'
        $user->update([
            'suspend_ban_reason' => $ban_reason,
            "status" => StatusConstants::BANNED,
            'remember_token' => Str::random(60) // Reset remember_token to invalidate web sessions
        ]);

        // Send a notification to the user
        Notification::send($user, new BannedUserNotification($user, $ban_reason));
        broadcast(new RefreshNotification($user->id));

        // Revoke all access tokens (API logout)
        $user->tokens()->delete();

        // Optionally log the ban activity
        (new ActivityLogService)
            ->setEvent("banned")
            ->setTitle("User banned")
            ->setDescription((auth()->user()?->email) . " banned a user")
            ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
            ->setActivity(ActivitiesConstants::STRIKED_USER)
            ->setModel(User::class, $user->id)
            ->setAdmin(auth()->user()?->id)
            ->setData(["User" => $user->refresh()->toArray()])
            ->setUrl(request()->fullUrl())
            ->log();

        return $user;
    }




    public function hidePost(Request $request, $id)
    {
        $user = $this->getById($id);
        // Check if user has posts
        $postCount = $user->posts()->count();
        if ($postCount > 0) {
            // Soft delete all posts
            $user->posts()->delete();
            // Send notification about the post suspension
            Notification::send($user, new PostSuspensionNotification($user));
            broadcast(new RefreshNotification($user->id));
        }
        // Refresh the user model
        $user->refresh();


        (new ActivityLogService)
            ->setEvent("hide_post")
            ->setTitle("Hid User Post")
            ->setDescription((auth()->user()?->email) . " hid a user post(s)")
            ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
            ->setActivity(ActivitiesConstants::HIDE_USER_POST)
            ->setModel(User::class, $user->id)
            ->setAdmin(auth()->user()?->id)
            ->setData([
                "User Post(s)" => $user->posts()->withTrashed()->get()->toArray(),
            ])
            ->setUrl(request()->fullUrl())
            ->log();

        return $user;
    }


    public function restorePost(Request $request, $id)
    {
        $user = $this->getById($id);
        // Check if user has posts that are soft deleted
        $user->posts()->onlyTrashed()->restore();
        // Send notification about the post restoration
        Notification::send($user, new PostRestorationNotification($user));
        broadcast(new RefreshNotification($user->id));

        // Refresh the user model
        $user->refresh();

        (new ActivityLogService)
            ->setEvent("restore_post")
            ->setTitle("Restored User Post")
            ->setDescription((auth()->user()?->email) . " restored a user post(s)")
            ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
            ->setActivity(ActivitiesConstants::HIDE_USER_POST)
            ->setModel(User::class, $user->id)
            ->setAdmin(auth()->user()?->id)
            ->setData([
                "User Post(s)" => $user->posts()->withTrashed()->get()->toArray(),
            ])
            ->setUrl(request()->fullUrl())
            ->log();
        return $user;
    }

    public function deleteUserPostsPermanently(Request $request, $id)
    {
        $user = $this->getById($id);
        // Retrieve the soft-deleted posts before they are permanently deleted
        $deletedPosts = $user->posts()->onlyTrashed()->get();
        $user->posts()->onlyTrashed()->forceDelete();
        // Send notification about the post restoration
        Notification::send($user, new PostsRemovedFromApplicationNotification($user));
        broadcast(new RefreshNotification($user->id));

        // Refresh the user model
        $user->refresh();

        (new ActivityLogService)
            ->setEvent("deleted")
            ->setTitle("Deleted User Post(s)")
            ->setDescription((auth()->user()?->email) . " deleted user post(s)")
            ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
            ->setActivity(ActivitiesConstants::DELETE_USER_POST)
            ->setModel(User::class, $user->id)
            ->setAdmin(auth()->user()?->id)
            ->setData([
                "User Post(s)" => $deletedPosts->toArray(),
            ])
            ->setUrl(request()->fullUrl())
            ->log();
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

    public static function search($username)
    {
        $users = User::search($username)->unblocked()->get();
        return $users;
    }
}
