<?php

namespace App\Services\PostCategory;

use App\Constants\General\StatusConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\PostCategory;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PostCategoryService
{
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
            "category_id" => "bail|nullable|exists:product_categories,id",
            "name" => "bail|required|string",
            "description" => "bail|nullable|string",
            "status" => "bail|required|string|" . Rule::in(StatusConstants::ACTIVE_OPTIONS),
            "image" => "bail|nullable|string|" . Rule::requiredIf(empty($id)),
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        return $validator->validated();
    }

    public static function create(array $data)
    {
        $data = self::validate($data);
        $category =  PostCategory::create($data);
        return $category;
    }

    public static function update(array $data, $id)
    {
        $data = self::validate($data, $id);
        $category = self::getById($id);
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


    public static function list()
    {
        $categories = PostCategory::orderBy("category_id");
        return $categories;
    }

    public static function parentList()
    {
        $categories = PostCategory::whereNull("category_id")->orderBy("category_id");
        return $categories;
    }
}
