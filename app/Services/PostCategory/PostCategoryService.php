<?php

namespace App\Services\PostCategory;

use App\Constants\ActivityLog\ActivitiesConstants;
use App\Constants\ActivityLog\ActivityLogConstants;
use App\Constants\General\StatusConstants;
use App\Constants\Media\FileConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\MethodsHelper;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\MergeCategory;
use App\Models\PostCategory;
use App\Models\User;
use App\Models\UserInterest;
use App\Services\ActivityLog\ActivityLogService;
use Illuminate\Database\Eloquent\Model;
use App\Services\Media\FileService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PostCategoryService
{
    protected $file_service;
    public function __construct()
    {
        $this->file_service = new FileService;
    }

    public static function getById($id, $column = "id"): PostCategory
    {
        $category = PostCategory::where($column, $id)->first();
        if (empty($category)) {
            throw new ModelNotFoundException("Category not found");
        }
        return $category;
    }

    public static function validate($data, $id = null)
    {
        $validator = Validator::make($data, [
            "category_id" => "bail|nullable|exists:post_categories,id",
            "name" => "bail|required|string",
            "description" => "bail|nullable|string",
            "status" => "bail|required|string|" . Rule::in(StatusConstants::ACTIVE_OPTIONS),
            "image" => "bail|nullable|" . Rule::requiredIf(empty($id)),
            "icon_image" => "bail|nullable|" . Rule::requiredIf(empty($id)),
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        return $validator->validated();
    }

    public function create(array $data)
    {
        $data = self::validate($data);

        if (!empty($background_image = $data["image"] ?? null)) {
            $data["image"] = $this->file_service->saveFromFileIntoStorage($background_image, FileConstants::CATEGORY_PATH, null, auth()->id());
        }

        if (!empty($icon_image = $data["icon_image"] ?? null)) {
            $data["icon_image"] = $this->file_service->saveFromFileIntoStorage($icon_image, FileConstants::CATEGORY_PATH, null, auth()->id());
        }

        $data["uuid"] = MethodsHelper::getRandomToken(10);
        $category = PostCategory::create($data);

        (new ActivityLogService)
            ->setEvent("created")
            ->setTitle("Category Created")
            ->setDescription((auth()->user()?->full_name . " created a category"))
            ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
            ->setActivity(ActivitiesConstants::CATEGORY_CREATED)
            ->setModel(PostCategory::class, $category->id)
            ->setAdmin(auth()->user()?->id)
            ->setData([
                "Post Category" => $category->refresh()->toArray(),
            ])
            ->setUrl(request()->fullUrl())
            ->log();
        return $category;
    }

    public function update(array $data, $id)
    {
        $data = self::validate($data, $id);
        $category = self::getById($id);

        $old_category_data = $category;

        if (!empty($background_image = $data["image"] ?? null)) {
            $data["image"] = $this->file_service->saveFromFileIntoStorage($background_image, FileConstants::CATEGORY_PATH, null, auth()->id());
        }

        if (!empty($icon_image = $data["icon_image"] ?? null)) {
            $data["icon_image"] = $this->file_service->saveFromFileIntoStorage($icon_image, FileConstants::CATEGORY_PATH, null, auth()->id());
        }

        $category->update($data);

        $new_category_data = $category;

        (new ActivityLogService)
            ->setEvent("updated")
            ->setTitle("Category Updated")
            ->setDescription((auth()->user()?->email) . " updated a category")
            ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
            ->setActivity(ActivitiesConstants::CATEGORY_UPDATED)
            ->setModel(PostCategory::class, $category->id)
            ->setAdmin(auth()->user()?->id)
            ->setData(
                ["Old Category Data" => $old_category_data->toArray()],
                ["Category" => $new_category_data->refresh()->toArray()]
            )
            ->setUrl(request()->fullUrl())
            ->log();

        return $category;
    }


    public static function delete($category_id)
    {
        DB::beginTransaction();
        try {
            $category = self::getById($category_id);
            $deleted_category = $category;
            $category->delete();
            DB::commit();
            (new ActivityLogService)
                ->setEvent("deleted")
                ->setTitle("Category Deleted")
                ->setDescription((auth()->user()?->full_name . " deleted a category"))
                ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
                ->setActivity(ActivitiesConstants::CATEGORY_DELETED)
                ->setModel(PostCategory::class, $category->id)
                ->setAdmin(auth()->user()?->id)
                ->setData([
                    "Category" =>  $deleted_category->toArray()
                ])
                ->setUrl(request()->fullUrl())
                ->log();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }


    public static function list(array $data = [])
    {
        $categories = PostCategory::with(["user"]);

        if (!empty($key = $data["search"] ?? null)) {
            $categories = $categories->where("name", "LIKE", "%$key%");
        }

        if (!empty($key = $data["category_id"] ?? null)) {
            $categories = $categories->where("category_id", $key);
        } else {
            $categories = $categories->whereNull("category_id");
        }

        if (!empty($key = $data["sort"] ?? null)) {
            if ($key == "popular") {
                $categories = $categories->withCount('posts')->orderBy('posts_count', 'desc');
            }
        }

        return $categories;
    }

    public static function parentList()
    {
        $categories = PostCategory::whereNull("category_id")->orderBy("category_id");
        return $categories;
    }

    public static function subCategoryList(array $data = [])
    {
        $categories = PostCategory::with(["user"])->whereNotNull("category_id");

        if (!empty($key = $data["search"] ?? null)) {
            $categories = $categories->where("name", "LIKE", "%$key%");
        }

        if (!empty($key = $data["category_id"] ?? null)) {
            $categories = $categories->where("category_id", $key);
        }

        if (!empty($key = $data["sort"] ?? null)) {
            if ($key == "popular") {
                $categories = $categories->withCount('posts')->orderBy('posts_count', 'desc');
            }
        }

        if (!empty($key = $data["following"] ?? null)) {
            $interests = UserInterest::where("user_id", auth("sanctum")->id())->pluck("category_id")->toArray();
            $categories = $categories->whereIn("id", $interests)->orderBy("category_id");
        }

        return $categories;
    }

    public static function following()
    {
        $interests = UserInterest::where("user_id", auth("sanctum")->id())->pluck("category_id")->toArray();
        $categories = PostCategory::whereIn("id", $interests)->orderBy("category_id");
        return $categories;
    }

    public function mergeCatWithGroups(array $data = [])
    {
        $validator = Validator::make($data, [
            "category_id" => "bail|required|exists:post_categories,id",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $category = self::getById($data["category_id"]);
        $categories = $this->parseMergedCategories($category);
        $groups = $this->parseMergedGroups($category);

        $categoriesCollection = collect($categories);
        $groupsCollection = collect($groups);

        $merged_category = $categoriesCollection->merge($groupsCollection);

        return $merged_category->map(function ($item) {
            return new MergeCategory($item);
        });
    }

    public function parseMergedCategories($parent_category)
    {
        $categories = PostCategory::where("category_id", $parent_category->id)->withCount("interests")->status()->get();
        $interests = UserInterest::where("user_id", auth("sanctum")->id())->pluck("category_id")->toArray();

        return $categories->map(function ($category) use ($parent_category, $interests) {
            $is_following = in_array($category->id, $interests ?? []);
            return [
                "id" => $category->id,
                "name" => $category->name,
                "uuid" => $category->uuid,
                "background_image" => $category->image,
                "icon_image" => $category->icon_image,
                "description" => $category->description,
                "is_following" => $is_following,
                "parent_category" => [
                    "id" => $parent_category->id,
                    "name" => $parent_category->name
                ],
                "type" => "Category",
                "followers_count" => $category->interests_count,
                "created_at" => formatDate($category->created_at),
            ];
        });
    }

    public function parseMergedGroups($parent_category)
    {
        // Fetch group members in one query
        $group_members = GroupMember::where([
            "user_id" => auth("sanctum")->id(),
        ])->get()->keyBy('group_id'); // Key by group_id for faster lookups

        // Fetch groups with their member count in one query
        $groups = Group::where("category_id", $parent_category->id)
            ->withCount("members")
            ->status()
            ->get();

        return $groups->map(function ($group) use ($parent_category, $group_members) {
            // Fetch the member from the preloaded group members
            $member = $group_members->get($group->id);

            return [
                "id" => $group->id,
                "type" => "Group",
                "name" => $group->name,
                "uuid" => $group->uuid,
                "background_image" => $group->image,
                "icon_image" => null,
                "description" => $group->description,
                "is_following" => $member?->status == StatusConstants::ACTIVE,
                "is_suspended" => $member?->status == StatusConstants::SUSPENDED,
                "group_access" => $group->group_access,
                "parent_category" => [
                    "id" => $parent_category->id,
                    "name" => $parent_category->name
                ],
                "followers_count" => $group->members_count,
                "created_at" => formatDate($group->created_at),
            ];
        });
    }
}
