<?php

namespace App\Http\Controllers\Api\V2\PlatformAdmin;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\PrivacyPolicy;
use App\Models\TermAndCondition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Exception;

/**
 * Write side of the existing public read-only legal documents
 * (Api\V2\Legal\LegalController serves these to end users).
 */
class PlatformLegalController extends Controller
{
    private const MODELS = ['privacy' => PrivacyPolicy::class, 'terms' => TermAndCondition::class];

    public function show(string $slug)
    {
        try {
            $model = self::MODELS[$slug] ?? null;
            if (empty($model)) {
                return ApiHelper::problemResponse("Unknown legal document", ApiConstants::BAD_REQ_ERR_CODE, null, null);
            }

            $document = $model::latest('id')->first();
            return ApiHelper::validResponse("Document returned successfully", $document);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function update(Request $request, string $slug)
    {
        try {
            $model = self::MODELS[$slug] ?? null;
            if (empty($model)) {
                return ApiHelper::problemResponse("Unknown legal document", ApiConstants::BAD_REQ_ERR_CODE, null, null);
            }

            $validator = Validator::make($request->all(), [
                "body" => "required|string",
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $document = $model::create([
                "body" => $request->input('body'),
                "status" => "Active",
                "author" => auth()->user()?->email,
                "document" => $request->input('document'),
            ]);

            return ApiHelper::validResponse("Document updated", $document);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
