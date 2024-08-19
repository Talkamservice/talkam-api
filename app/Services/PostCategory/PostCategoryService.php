<?php

namespace App\Services\PostCategory;

use App\Constants\General\StatusConstants;
use App\Constants\Media\FileConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\Group;
use App\Models\MergeCategory;
use App\Models\PostCategory;
use App\Models\UserInterest;
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

    public static function getById($id): PostCategory
    {
        $category = PostCategory::find($id);
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

        $category = PostCategory::create($data);
        return $category;
    }

    public function update(array $data, $id)
    {
        $data = self::validate($data, $id);
        $category = self::getById($id);

        if (!empty($background_image = $data["image"] ?? null)) {
            $data["image"] = $this->file_service->saveFromFileIntoStorage($background_image, FileConstants::CATEGORY_PATH, null, auth()->id());
        }

        if (!empty($icon_image = $data["icon_image"] ?? null)) {
            $data["icon_image"] = $this->file_service->saveFromFileIntoStorage($icon_image, FileConstants::CATEGORY_PATH, null, auth()->id());
        }

        $category->update($data);
        return $category->refresh();
    }

    public static function delete($category_id)
    {
        DB::beginTransaction();
        try {
            $category = self::getById($category_id);
            $category->delete();
            DB::commit();
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
        $categories = PostCategory::with(["user"]);

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

        $categories = $categories->whereNotNull("category_id");
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

        return $categories->map(function ($category) use ($parent_category) {
            return [
                "name" => $category->name,
                "parent_category" => [
                    "id" => $parent_category->id,
                    "name" => $parent_category->name
                ],
                "followers_count" => $category->interests_count,
                "created_at" => formatDate($category->created_at),
            ];
        });
    }

    public function parseMergedGroups($parent_category)
    {
        $groups = Group::where("category_id", $parent_category->id)->withCount("members")->status()->get();
        return $groups->map(function ($group) use ($parent_category) {
            return [
                "name" => $group->name,
                "followers_count" => $group->members_count,
                "created_at" => formatDate($group->created_at),
            ];
        });
    }
}
