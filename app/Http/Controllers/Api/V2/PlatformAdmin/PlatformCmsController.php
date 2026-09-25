<?php

namespace App\Http\Controllers\Api\V2\PlatformAdmin;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Exception;

/**
 * Net-new admin CRUD over Article — JournalService is public read-only
 * (published() scope only), no admin write surface existed before this.
 */
class PlatformCmsController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Article::query();

            if (!empty($status = $request->input('status'))) {
                $query->where('status', $status);
            }

            $articles = $query->orderByDesc('published_at')->paginate(20);
            return ApiHelper::validResponse("Articles returned successfully", $articles);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function show($id)
    {
        try {
            return ApiHelper::validResponse("Article returned successfully", Article::findOrFail($id));
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $this->validated($request);
            $validated['slug'] = $validated['slug'] ?? Str::slug($validated['title']) . '-' . Str::random(6);
            $validated['author'] = $validated['author'] ?? auth()->user()?->email;

            $article = Article::create($validated);
            return ApiHelper::validResponse("Article created", $article);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $article = Article::findOrFail($id);
            $article->update($this->validated($request, false));
            return ApiHelper::validResponse("Article updated", $article->refresh());
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function destroy($id)
    {
        try {
            Article::findOrFail($id)->delete();
            return ApiHelper::validResponse("Article deleted", []);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    private function validated(Request $request, bool $require = true): array
    {
        $rule = $require ? "required" : "nullable";
        $validator = Validator::make($request->all(), [
            "title" => "$rule|string|max:200",
            "slug" => "nullable|string|max:220|unique:articles,slug",
            "category" => "nullable|string|max:100",
            "tone" => "nullable|string|max:50",
            "cover" => "nullable|string",
            "excerpt" => "nullable|string|max:400",
            "author" => "nullable|string|max:150",
            "author_initials" => "nullable|string|max:5",
            "author_role" => "nullable|string|max:100",
            "author_bio" => "nullable|string|max:300",
            "read_time" => "nullable|string|max:20",
            "display_date" => "nullable|string|max:50",
            "body" => "$rule|array",
            "sort_order" => "nullable|integer",
            "published_at" => "nullable|date",
            "status" => "nullable|string|in:" . Article::STATUS_PUBLISHED . ",draft",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return collect($validator->validated())->filter(fn ($v) => $v !== null)->all();
    }
}
