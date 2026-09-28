<?php

namespace App\Http\Controllers\Api\V2\Business;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\Business\OrgAggregateService;
use Illuminate\Http\Request;
use Exception;

/**
 * Anonymised, company-wide insight for the admin dashboard.
 *
 * Every figure served here comes out of OrgAggregateService, which is the single
 * place the <5 cohort suppression is applied. Nothing individual is reachable
 * through this controller by construction — the services aggregate in SQL and
 * never return a user id, a session id or an event timestamp.
 */
class AdminInsightsController extends Controller
{
    public function overview(Request $request)
    {
        try {
            return ApiHelper::validResponse(
                "Overview returned successfully",
                OrgAggregateService::overview($request->attributes->get("organization"))
            );
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    /**
     * The aggregate the onboarding self-check promised employees: a company-wide
     * pattern once at least five people have responded, and nothing before that.
     */
    public function teamNeeds(Request $request)
    {
        try {
            return ApiHelper::validResponse(
                "Team needs returned successfully",
                OrgAggregateService::teamNeeds($request->attributes->get("organization"))
            );
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function reports(Request $request)
    {
        try {
            $organization = $request->attributes->get("organization");

            return ApiHelper::validResponse("Reports returned successfully", [
                "monthly_trend" => OrgAggregateService::monthlyTrend($organization),
                "departments" => OrgAggregateService::departmentRollup($organization),
                "reports" => [
                    [
                        "key" => "usage",
                        "title" => "Monthly Usage Report",
                        "body" => "Session counts, active users, engagement rate by department. Anonymised.",
                    ],
                    [
                        "key" => "wellness",
                        "title" => "Wellness Trend Analysis",
                        "body" => "Topic trends, repeat sessions, mood indicators. 90-day rolling data.",
                    ],
                    [
                        "key" => "roi",
                        "title" => "ROI & Productivity Report",
                        "body" => "Estimated absenteeism reduction, cost per session, wellness ROI calculation.",
                    ],
                ],
            ]);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    /**
     * CSV of an aggregate report. Rows are months or departments — never people.
     */
    public function download(Request $request, string $key)
    {
        try {
            $organization = $request->attributes->get("organization");

            $rows = match ($key) {
                "usage" => self::usageRows($organization),
                "wellness" => self::wellnessRows($organization),
                "roi" => self::roiRows($organization),
                default => null,
            };

            if ($rows === null) {
                return ApiHelper::problemResponse("Unknown report", ApiConstants::NOT_FOUND_ERR_CODE, null, null);
            }

            $filename = "talkam-{$key}-" . now()->format("Y-m") . ".csv";

            return response()->streamDownload(function () use ($rows) {
                $out = fopen("php://output", "w");
                foreach ($rows as $row) {
                    fputcsv($out, $row);
                }
                fclose($out);
            }, $filename, ["Content-Type" => "text/csv"]);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    private static function usageRows($organization): array
    {
        $rows = [["Month", "Sessions"]];
        $trend = OrgAggregateService::monthlyTrend($organization);

        if ($trend["suppressed"]) {
            return [["Not enough data"], ["Fewer than " . OrgAggregateService::cohortFloor() . " employees — figures are withheld to protect anonymity."]];
        }

        foreach ($trend["value"] as $point) {
            $rows[] = [$point["month"], $point["value"]];
        }

        return $rows;
    }

    private static function wellnessRows($organization): array
    {
        $needs = OrgAggregateService::teamNeeds($organization);

        if ($needs["suppressed"]) {
            return [["Not enough data"], ["Fewer than " . OrgAggregateService::cohortFloor() . " responses — figures are withheld to protect anonymity."]];
        }

        $rows = [["Focus area", "Share of responses (%)"]];
        foreach ($needs["value"] as $need) {
            $rows[] = [$need["label"], $need["percent"]];
        }

        return $rows;
    }

    private static function roiRows($organization): array
    {
        $overview = OrgAggregateService::overview($organization);
        $roi = $overview["roi"];

        if ($roi["suppressed"]) {
            return [["Not enough data"], ["Fewer than " . OrgAggregateService::cohortFloor() . " employees — figures are withheld to protect anonymity."]];
        }

        $v = $roi["value"];

        return [
            ["Metric", "Value"],
            ["Sessions this month", $v["sessions"]],
            ["Absenteeism days avoided per session", $v["days_per_session"]],
            ["Average daily productivity value", $v["daily_value"]],
            ["Gross value", $v["gross_value"]],
            ["Program cost", $v["program_cost"]],
            ["Net ROI", $v["net_roi"]],
            ["Return multiple", $v["multiple"]],
        ];
    }
}
