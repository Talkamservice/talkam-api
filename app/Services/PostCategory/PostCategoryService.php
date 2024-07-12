<?php

namespace App\Services\PostCategory;

use App\Constants\General\StatusConstants;
use App\Constants\Media\FileConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\PostCategory;
use App\Services\Media\FileService;
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

        $category =  PostCategory::create($data);
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
        $category = self::getById($category_id);

        if ($category->posts->isNotEmpty()) {
            throw new InvalidRequestException("Category cannot be deleted because it has some posts in it");
        }

        $category->delete();
    }


    public static function list(array $data = [])
    {
        $categories = PostCategory::with(["user"]);

        if (!empty($key = $data["search"] ?? null)) {
            $categories = $categories->where("name", "LIKE", "%$key%");
        }

        if (!empty($key = $data["category_id"] ?? null)) {
            $categories = $categories->where("category_id", "%$key%");
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
}
