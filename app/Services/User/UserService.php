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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
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
        (new ActivityLogService)
            ->setEvent("created")
            ->setTitle("User Account Created")
            ->setDescription((auth()->user()->email) . " create a user account")
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
                "username" => "nullable|unique:users,username,$id",
                "age" => "nullable|numeric",
                "password" => "nullable|string|confirmed",
            ], [
                "username.unique" => "The username has already been taken"
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
                ->setDescription((auth()->user()->email) . " update their account")
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
                ->setDescription((auth()->user()->email) . " erased their data")
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
                ->setDescription((auth()->user()->email) . " deleted their account")
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
                ->setDescription((auth()->user()->email) . " delete a user")
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
                'suspension_duration' => $suspension_end,
                "status" => $status
            ]);
            Notification::send($user, new SuspendUserNotification($user, $user->status, $suspension_reason, $suspension_end->toFormattedDateString()));
            broadcast(new RefreshNotification($user->id));
            $user->refresh();
            (new ActivityLogService)
                ->setEvent("suspend")
                ->setTitle("User Suspended")
                ->setDescription((auth()->user()->email) . " suspend a user")
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
                "status" => $status
            ]);
            Notification::send($user, new SuspendUserNotification($user, $user->status, null, null));
            broadcast(new RefreshNotification($user->id));
            $user->refresh();
            (new ActivityLogService)
                ->setEvent("unsuspend")
                ->setTitle("User Unsuspended")
                ->setDescription((auth()->user()->email) . " unsuspend a user")
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
            ->setDescription((auth()->user()->email) . " strike a user")
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
        $user = $this->getById($id);
        $ban_reason = $request->input('suspend_ban_reason');
        // No need for duration; the ban will be permanent
        $user->update([
            'suspend_ban_reason' => $ban_reason,
            "status" => StatusConstants::BANNED
        ]);

        Notification::send($user, new BannedUserNotification($user, $ban_reason));
        broadcast(new RefreshNotification($user->id));

        $user->refresh();

        (new ActivityLogService)
            ->setEvent("banned")
            ->setTitle("User banned")
            ->setDescription((auth()->user()->email) . " banned a user")
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
            ->setTitle("Hide User Post")
            ->setDescription((auth()->user()->email) . " hide user post")
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
            ->setTitle("Restore User Post")
            ->setDescription((auth()->user()->email) . " restore user post")
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
            ->setDescription((auth()->user()->email) . " deleted user post(s)")
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
}
