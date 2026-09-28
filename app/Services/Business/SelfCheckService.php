<?php

namespace App\Services\Business;

use App\Constants\Business\OrganizationConstants;
use App\Models\EmployeeSelfCheck;
use App\Models\OrganizationMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Onboarding self check-in.
 *
 * The individual answers are the employee's own — they are never readable by
 * an admin at row level. §03's admin surface reads only a company-wide
 * aggregate off organization_id, suppressed below the configured cohort floor.
 */
class SelfCheckService
{
    public function save(OrganizationMember $membership, array $data): array
    {
        $rules = ["answers" => "required|array:" . implode(",", OrganizationConstants::SELF_CHECK_CATEGORIES)];

        foreach (OrganizationConstants::SELF_CHECK_CATEGORIES as $category) {
            $rules["answers.{$category}"] = "required|integer|min:0|max:" . OrganizationConstants::SELF_CHECK_MAX_SCORE;
        }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $answers = $validator->validated()["answers"];

        DB::beginTransaction();
        try {
            foreach ($answers as $category => $score) {
                EmployeeSelfCheck::updateOrCreate(
                    [
                        "user_id" => $membership->user_id,
                        "category" => $category,
                    ],
                    [
                        "organization_id" => $membership->organization_id,
                        "score" => $score,
                        "answered_at" => now(),
                    ]
                );
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }

        return self::state($membership, $answers);
    }

    /**
     * The "we'll suggest therapists specialising in X first" line: the
     * highest-scoring category, ties resolved by the deck's display order.
     */
    public static function primaryCategory(array $answers): ?string
    {
        $best = null;
        $best_score = -1;

        foreach (OrganizationConstants::SELF_CHECK_CATEGORIES as $category) {
            $score = (int) ($answers[$category] ?? 0);

            if ($score > $best_score) {
                $best = $category;
                $best_score = $score;
            }
        }

        return $best;
    }

    public static function state(OrganizationMember $membership, ?array $answers = null): array
    {
        if ($answers === null) {
            $answers = EmployeeSelfCheck::where("user_id", $membership->user_id)
                ->pluck("score", "category")
                ->all();
        }

        $primary = self::primaryCategory($answers);

        return [
            "answers" => $answers,
            "primary_category" => $primary,
            "primary_concern" => $primary
                ? (OrganizationConstants::SELF_CHECK_CONCERNS[$primary] ?? null)
                : null,
            "completed" => count($answers) === count(OrganizationConstants::SELF_CHECK_CATEGORIES),
        ];
    }
}
