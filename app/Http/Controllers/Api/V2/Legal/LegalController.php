<?php

namespace App\Http\Controllers\Api\V2\Legal;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Legal\LegalDocumentResource;
use App\Models\PrivacyPolicy;
use App\Models\TermAndCondition;
use Exception;

/**
 * The web legal pages (web §06) — public, read-only structured documents.
 *
 * Reuses the existing PrivacyPolicy / TermAndCondition tables (the same rows
 * mobile reads as a `body` blob); this endpoint returns their structured
 * `document` for the browser layout.
 */
class LegalController extends Controller
{
    // Web slug → the model that owns that document.
    const DOCUMENTS = [
        "privacy" => PrivacyPolicy::class,
        "terms" => TermAndCondition::class,
    ];

    public function show(string $slug)
    {
        try {
            $model = self::DOCUMENTS[$slug] ?? null;

            if (empty($model)) {
                return ApiHelper::problemResponse(
                    "Legal document not found",
                    ApiConstants::NOT_FOUND_ERR_CODE,
                    null,
                    null
                );
            }

            // Newest row wins; order by id so it stays deterministic when two
            // documents share a created_at second.
            $record = $model::whereNotNull("document")->latest("id")->first();

            if (empty($record)) {
                return ApiHelper::problemResponse(
                    "Legal document not found",
                    ApiConstants::NOT_FOUND_ERR_CODE,
                    null,
                    null
                );
            }

            return ApiHelper::validResponse(
                "Legal document returned successfully",
                LegalDocumentResource::make($record)->resolve()
            );
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
