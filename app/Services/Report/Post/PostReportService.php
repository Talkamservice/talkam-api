<?php

namespace App\Services\Report\Post;

use App\Exceptions\General\ModelNotFoundException;
use App\Models\PostReport;
use Illuminate\Http\Request;

class PostReportService
{
    public static function getById($id): PostReport
    {
        $report = PostReport::find($id);
        if (empty($report)) {
            throw new ModelNotFoundException("Report not found");
        }
        return $report;
    }

    public static function changeStatus(Request $request, $report_id)
    {
        // Find the report by ID
        $report = self::getById($report_id);
        // Validate the status input if needed
        $request->validate([
            'status' => 'required|string|in:Pending,Approved,Suspended,Deleted',
        ]);
        // Update the status
        $report->update(['status' => $request->input('status')]);
        // Return a response
        return $report;
    }

    public static function delete($report_id)
    {
        $report = self::getById($report_id);
        $report->delete();
    }
}
