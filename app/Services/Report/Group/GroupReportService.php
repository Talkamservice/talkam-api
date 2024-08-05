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
            "reason" => "required|string",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

   public function

    public static function delete($report_id)
    {
        $report = self::getById($report_id);
        $report->delete();
    }
}
