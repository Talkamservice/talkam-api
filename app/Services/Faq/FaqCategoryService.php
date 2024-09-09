<?php

namespace App\Services\Faq;

use App\Constants\ActivityLog\ActivitiesConstants;
use App\Constants\ActivityLog\ActivityLogConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\FaqCategory;
use App\Services\ActivityLog\ActivityLogService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FaqCategoryService
{

    public static function getById($id): FaqCategory
    {
        $faq_category = FaqCategory::find($id);
        if (empty($faq_category)) {
            throw new ModelNotFoundException("Category not found");
        }
        return $faq_category;
    }

    public static function validate($data, $id = null)
    {
        $validator = Validator::make($data, [
            "name" => "bail|nullable|string",
            'status' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public  function store(array $data)
    {
        $data = self::validate($data);
        $faq_category =  FaqCategory::create($data);
        (new ActivityLogService)
        ->setEvent("created")
        ->setTitle("Created FAQ Category")
        ->setDescription(auth()->user()?->full_name . " created an FAQ category")
        ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
        ->setActivity(ActivitiesConstants::CREATED_FAQ_CATEGORY)
        ->setModel(FaqCategory::class, $faq_category->id)
        ->setAdmin(auth()->user()->id)
        ->setData(
            [
                "FAQ Category" => $faq_category->refresh()->toArray()
            ])
        ->setUrl(request()->fullUrl())
        ->log();
        return $faq_category;
    }

    public function update(array $data, $id)
    {
        $data = self::validate($data, $id);
        $faq_category = self::getById($id);
        $old_faq_category = $faq_category;

        $faq_category->update($data);
        (new ActivityLogService)
        ->setEvent("updated")
        ->setTitle("Updated FAQ Category")
        ->setDescription(auth()->user()?->full_name . " updated an FAQ Category")
        ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
        ->setActivity(ActivitiesConstants::UPDATED_FAQ_CATEGORY)
        ->setModel(FaqCategory::class, $faq_category->id)
        ->setAdmin(auth()->user()->id)
        ->setData(
            [
                "FAQ Category" => $faq_category->refresh()->toArray()
            ],
            [
                "Old FAQ Category" => $old_faq_category->toArray()
            ]
        )
        ->setUrl(request()->fullUrl())
        ->log();
        return $faq_category->refresh();
    }

    public function delete($faq_category_id)
    {
        $faq_category = self::getById($faq_category_id);
        $faq_category->delete();
    }

    public static function list()
    {
        $faq_categorys = FaqCategory::latest();
        return $faq_categorys;
    }
}
