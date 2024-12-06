<?php

namespace App\Services\Group;

use App\Constants\Account\User\UserConstants;
use App\Constants\General\StatusConstants;
use App\Events\RefreshNotification;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\MethodsHelper;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Promotion;
use App\Models\User;
use App\Notifications\Group\JoinGroupRequestNotification;
use App\Notifications\Group\JoinGroupRequestStatusNotification;
use App\Services\Guideline\GuidelineService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GroupService
{
    public $user;

    public static function getById($key, $column = "id"): Group
    {
        $group = Group::where($column, $key)->first();
        if (empty($group)) {
            throw new ModelNotFoundException("Group not found");
        }
        return $group;
    }

    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    public static function getGroupAdmins($group_id)
    {
        $group = self::getById($group_id);
        $group_admins = GroupMember::where([
            "group_id" => $group->id,
            "role" => UserConstants::ADMIN
        ])->get();

        return $group_admins;
    }

    public static function getGroupOwner($group_id)
    {
        $group = self::getById($group_id);
        $group_owner = GroupMember::where([
            "group_id" => $group->id,
            "role" => UserConstants::OWNER
        ])->first();

        return $group_owner;
    }

    public static function validate(array $data, $id = null)
    {
        $validator = Validator::make($data, [
            "category_id" => "nullable|exists:post_categories,id|" . Rule::requiredIf(empty($id)),
            "name" => "required|string",
            "description" => "nullable|string",
            "status" => "nullable|string",
            "about" => "nullable|string",
            "image" => "nullable|string",
            "rules_summary" => "nullable|string",
            "tags" => "nullable|array",
            "tags.*" => "string",
            "can_post" => "nullable|numeric",
            "group_access" => "nullable|string|in:Opened,Closed,Approval",
            "guidelines" => "nullable|array",
            "guidelines.*.title" => "required|string",
            "guidelines.*.description" => "required|string",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }


    public function create(array $data)
    {
        DB::beginTransaction();
        try {
            $data = $this->validate($data);
            $user = $this->user ?? auth()->user();
            $data["created_by"] = $user?->id;
            $data["uuid"] = self::generateUniqueId();

            $guidelines = $data["guidelines"] ?? [];
            unset($data["guidelines"]);

            $group = Group::create($data);

            (new GroupMemberService)->create([
                "group_id" => $group->id,
                "user_id" => $user?->id,
                "role" => UserConstants::OWNER,
                "status" => StatusConstants::ACTIVE
            ]);

            if (isset($guidelines)) {
                foreach ($guidelines as $key => $guideline) {
                    (new GuidelineService)->create([
                        "group_id" => $group->id,
                        ...(array) $guideline
                    ]);
                }
            }

            $this->notify($group);

            if ($group->group_access == "Opened") {
                $group->user?->increment("public_group_count");
            }

            DB::commit();
            return $group;
        } catch (Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function update(array $data, $id)
    {
        DB::beginTransaction();
        try {
            $data = $this->validate($data, $id);

            $group = self::getById($id);
            $group->update($data);

            DB::commit();
            return $group->refresh();
        } catch (Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }
    public function notify($group) {}

    public function generateUniqueId($length = 6)
    {
        $uuid = "G-" . MethodsHelper::generateRandomDigits($length);
        $check = Group::where("uuid", $uuid)->count();
        if ($check > 0) {
            return self::generateUniqueId($length);
        }
        return $uuid;
    }

    public static function list(array $data = [])
    {
        $builder = Group::with("creator");

        // Apply filters as before

        if (!empty($key = $data["search"] ?? null)) {
            $builder = $builder->search($key);
        }

        // Apply status filter here, before pagination

        if (!empty($key = $data["status"] ?? null)) {
            $builder = $builder->where("status", $key); // Apply the status filter on the query
            $builder = $builder->where("status", $key);
        }


        if (!empty($key = $data["recommend"] ?? null)) {
            if (auth("sanctum")->check()) {
                $category_ids = auth("sanctum")->user()->interests->pluck("category_id")->toArray();
                $builder = (count($category_ids) > 0) ? $builder->whereIn("id", $category_ids ?? []) : $builder->withCount("members")->orderBy("members_count", "desc");
            }
        }

        if (!empty($key = $data["category_id"] ?? null)) {
            $builder = $builder->where("category_id", $key);
        }

        if (!empty($key = $data["tab"] ?? null)) {
            $builder = match ($key) {
                "latest" => $builder->latest(),
                "popular" => $builder->orderBy("name", "asc"),
                    // "popular" => $builder->withCount("members")->orderBy("members_count", "desc"),
                default => $builder->inRandomOrder()
            };
        }
        return $builder;
    }

    public function getByPromotedGroups()
    {
        $builder = Group::with("creator")
            ->whereHas('promotions', function ($query) {
                $query->whereNull('post_id') // Ensure `post_id` is NULL
                    ->where(function ($query) {
                        // Ensure promotion has not expired
                        $query->whereRaw('DATE_ADD(created_at, INTERVAL duration DAY) >= ?', [now()]);
                    })
                    ->where('status', StatusConstants::ACTIVE); // Ensure promotion is active
            }); // Collection of groups
            
        if (!empty($key = $data["search"] ?? null)) {
            $builder = $builder->search($key);
        }

        // Apply status filter here, before pagination

        if (!empty($key = $data["status"] ?? null)) {
            $builder = $builder->where("status", $key); // Apply the status filter on the query
            $builder = $builder->where("status", $key);
        }

        return $builder;
    }

    // public static function interleavePromotedGroups($regularGroups)
    // {
    //     // Fetch promoted groups
    //     $promotedGroups = Promotion::whereNotNull('group_id')
    //         ->whereNull('post_id')
    //         ->where('status', '!=', StatusConstants::PENDING)
    //         ->with('group')
    //         ->get()
    //         ->filter(function ($promotion) {
    //             $expiresAt = Carbon::parse($promotion->created_at)->addDays($promotion->duration);
    //             return $expiresAt->greaterThanOrEqualTo(now());
    //         })
    //         ->sortByDesc(function ($promotion) {
    //             return $promotion->cost;
    //         })
    //         ->pluck('group'); // Collection of groups

    //     $interleavedGroups = [];
    //     $regularGroupIndex = 0;
    //     $promotedGroupIndex = 0;
    //     $regularGroupInterval = 5; // Number of regular groups between promoted groups

    //     // Interleave regular and promoted groups
    //     while ($regularGroupIndex < count($regularGroups)) {
    //         // Add up to 5 regular groups
    //         for ($i = 0; $i < $regularGroupInterval && $regularGroupIndex < count($regularGroups); $i++) {
    //             $interleavedGroups[] = $regularGroups[$regularGroupIndex];
    //             $regularGroupIndex++;
    //         }

    //         // Add one promoted group if available
    //         if ($promotedGroupIndex < $promotedGroups->count()) {
    //             $interleavedGroups[] = $promotedGroups[$promotedGroupIndex];
    //             $promotedGroupIndex++;
    //         }
    //     }

    //     // Append any remaining promoted groups (if necessary)
    //     while ($promotedGroupIndex < $promotedGroups->count()) {
    //         $interleavedGroups[] = $promotedGroups[$promotedGroupIndex];
    //         $promotedGroupIndex++;
    //     }

    //     return $interleavedGroups;
    // }





    public static function following(array $data = [])
    {
        $builder = self::list($data);

        $builder = $builder->whereRelation("members", function ($q) {
            $q->where(["user_id" => auth()->id()]);
            if (!empty($type = $data["type"] ?? null)) {
                if ($type == "all") {
                    $q->whereNotIn("status", [StatusConstants::BANNED]);
                } else {
                    $q->where(["user_id" => auth()->id()])
                        ->whereNotIn("status", [StatusConstants::SUSPENDED, StatusConstants::BANNED]);
                }
            }
        });

        return $builder;
    }

    public function requestAccess($id)
    {
        DB::beginTransaction();
        try {
            $member = GroupMember::where([
                "group_id" => $id,
                "user_id" => auth()->id(),
            ])->first();

            if (empty($member)) {
                $member = (new GroupMemberService)->create([
                    "group_id" => $id,
                    "user_id" => auth()->id(),
                    "role" => UserConstants::MEMBER,
                    "status" => StatusConstants::PENDING
                ]);
            }

            $member->update([
                "status" => StatusConstants::PENDING
            ]);

            $admins = GroupMember::where([
                "group_id" => $id,
            ])->whereIn("role", [UserConstants::ADMIN, UserConstants::OWNER])
                ->pluck("user_id")->toArray();

            $users = User::whereIn("id", $admins)->status()->get();

            if (empty($users)) {
                throw new InvalidRequestException("No admin found for this group");
            }

            Notification::send($users, new JoinGroupRequestNotification($member));
            foreach ($users as $key => $user) {
                broadcast(new RefreshNotification($user->id));
            }
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function updateAccessRequest(array $data)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($data, [
                "member_id" => "required|exists:group_members,id",
                "action" => "required|string|" . Rule::in([StatusConstants::APPROVED, StatusConstants::DECLINED]),
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $data = $validator->validated();

            $member = (new GroupMemberService())->getById($data["member_id"]);

            if ($data["action"] == StatusConstants::APPROVED) {
                Notification::send($member->user, new JoinGroupRequestStatusNotification($member, StatusConstants::APPROVED));
                $member->update([
                    "status" => StatusConstants::ACTIVE
                ]);
            }

            if ($data["action"] == StatusConstants::DECLINED) {
                Notification::send($member->user, new JoinGroupRequestStatusNotification($member, StatusConstants::DECLINED));
                $member->update([
                    "status" => StatusConstants::DECLINED
                ]);
            }

            broadcast(new RefreshNotification($member->user_id));

            if ($data["action"] == StatusConstants::DECLINED) {
                GroupMember::where([
                    "user_id" =>  $member->user_id,
                    "group_id" =>  $member->group_id,
                ])->delete();
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }
}
