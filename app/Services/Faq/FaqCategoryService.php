<?php

namespace App\Services\Faq;

use App\Exceptions\General\ModelNotFoundException;
use App\Models\Faq;
use App\Models\FaqCategory;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FaqCategoryService
{

    public static function getById($id): Faq
    {
        $faq_category = FaqCategory::find($id);
        if (empty($faq)) {
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
        return $faq_category;
    }

    public function update(array $data, $id)
    {
        $data = self::validate($data, $id);
        $faq_category = self::getById($id);

        $faq_category->update($data);
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
