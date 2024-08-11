<?php

namespace App\Services\Report\Group;

use App\Constants\General\StatusConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\GroupReport;
use App\Services\User\UserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class GroupReportService
{
    protected $user_service;
    public function __construct()
    {
        $this->user_service = new UserService;
    }

    public static function getById($id): GroupReport
    {
        $report = GroupReport::find($id);
        if (empty($report)) {
            throw new ModelNotFoundException("Report not found");
        }
        return $report;
    }

    public static function validate(array $data)
    {
        $validator = Validator::make($data, [
            "action" => "required|string|in:Suspended,Activated,Resolved"
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public static function reportGroup($data)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($data, [
                "group_id" => "required|numeric|exists:groups,id",
                "reason" => "required|string",
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $data = $validator->validated();

            $data["user_id"] = auth()->id();
            $report = GroupReport::create($data);

            DB::commit();
            return $report;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }


    public function changeStatus(array $data, $report_id)
    {
        DB::beginTransaction();
        try {
            $data = self::validate($data);
            $report = self::getById($report_id);

            if (in_array($report->status, [StatusConstants::RESOLVED])) {
                throw new InvalidRequestException("You cannot make changes you a resolved report");
            }

            if ($data["status"] == StatusConstants::SUSPENDED) {
                $this->user_service->suspend(StatusConstants::INACTIVE, $report->post->user_id);
            }

            if ($data["status"] == StatusConstants::ACTIVATED) {
                $this->user_service->suspend(StatusConstants::ACTIVE, $report->post->user_id);
            }

            $report->update([
                'status' => StatusConstants::RESOLVED
            ]);

            DB::commit();
            return $report->refresh();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public static function delete($report_id)
    {
        $report = self::getById($report_id);
        $report->delete();
    }
}
