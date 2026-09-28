<?php

namespace App\Http\Controllers\Api\V2\Journal;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Article\ArticleDetailResource;
use App\Http\Resources\Article\ArticleResource;
use App\Models\Article;
use App\Services\Journal\JournalService;
use Exception;

/**
 * The public TalkAM Journal (web §05) — read-only. No auth: editorial content.
 */
class ArticleController extends Controller
{
    public function index()
    {
        try {
            return ApiHelper::validResponse(
                "Articles returned successfully",
                ArticleResource::collection(JournalService::articles())
            );
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function show(string $slug)
    {
        try {
            $article = Article::published()->where("slug", $slug)->first();

            if (empty($article)) {
                return ApiHelper::problemResponse(
                    "Article not found",
                    ApiConstants::NOT_FOUND_ERR_CODE,
                    null,
                    null
                );
            }

            return ApiHelper::validResponse("Article returned successfully", [
                "article" => ArticleDetailResource::make($article)->resolve(),
                "related" => ArticleResource::collection(JournalService::related($article))->resolve(),
            ]);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
