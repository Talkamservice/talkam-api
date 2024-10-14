<?php

namespace App\Services\Promotion;

use App\Constants\ActivityLog\ActivitiesConstants;
use App\Constants\ActivityLog\ActivityLogConstants;
use App\Constants\General\StatusConstants;
use App\Constants\Media\FileConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\MethodsHelper;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\MergeCategory;
use App\Models\Promotion;
use App\Models\UserInterest;
use App\Services\ActivityLog\ActivityLogService;
use App\Services\Media\FileService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PromotionService
{
    public $user;
    public function __construct() {}

    public static function getById($id): Promotion
    {
        $promotion = Promotion::find($id);
        if (empty($promotion)) {
            throw new ModelNotFoundException("Promotion not found");
        }
        return $promotion;
    }

    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    public static function validate($data, $id = null)
    {
        $validator = Validator::make($data, [
            "post_id" => "bail|nullable|exists:posts,id",
            "group_id" => "bail|nullable|exists:groups,id",
            "state_id" => "bail|nullable|exists:states,id",
            "country_id" => "bail|nullable|exists:countries,id",
            "min_age" => "bail|required|string",
            "max_age" => "bail|required|string",
            "gender" => "bail|nullable|string",
            "daily_budget" => "bail|nullable|numeric",
            "duration" => "bail|nullable|numeric",
            "status" => "bail|required|string|" . Rule::in(StatusConstants::ACTIVE_OPTIONS),
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
            $data = self::validate($data);

            $user = $this->user = auth()->user();

            $promotion = Promotion::create(array_merge($data, [
                "user_id" => $user->id,
                "uuid" => MethodsHelper::getRandomToken(10),
            ]));

            (new ActivityLogService)
                ->setEvent("created")
                ->setTitle("Promotion Created")
                ->setDescription("{$promotion?->user?->getName()} promoted a post")
                ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
                ->setActivity(ActivitiesConstants::PROMOTION_CREATED)
                ->setModel(Promotion::class, $promotion->id)
                ->setAdmin(auth()->user()?->id)
                ->setData([
                    "promotion" => $promotion->refresh()->toArray(),
                ])
                ->setUrl(request()->fullUrl())
                ->log();

            DB::commit();
            return $promotion;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public static function delete($promotion_id)
    {
        DB::beginTransaction();
        try {
            $promotion = self::getById($promotion_id);
            $deleted_promotion = $promotion;
            $promotion->delete();

            (new ActivityLogService)
                ->setEvent("deleted")
                ->setTitle("Promotion Deleted")
                ->setDescription("{$promotion?->user?->getName()} deleted a promotion")
                ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
                ->setActivity(ActivitiesConstants::PROMOTION_DELETED)
                ->setModel(Promotion::class, $promotion->id)
                ->setAdmin(auth()->user()?->id)
                ->setData([
                    "Category" =>  $deleted_promotion->toArray()
                ])
                ->setUrl(request()->fullUrl())
                ->log();
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }


    public static function list(array $data = [])
    {
        $promotions = Promotion::with(["user"]);

        if (!empty($key = $data["search"] ?? null)) {
            $promotions = $promotions->where("name", "LIKE", "%$key%");
        }

        if (!empty($key = $data["post_id"] ?? null)) {
            $promotions = $promotions->where("post_id", $key);
        }

        if (!empty($key = $data["group_id"] ?? null)) {
            $promotions = $promotions->where("group_id", $key);
        }

        return $promotions;
    }
}
