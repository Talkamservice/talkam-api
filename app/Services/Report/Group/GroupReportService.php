<?php

namespace App\Services\Report\Group;

use App\Constants\General\NotificationConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberReport;
use App\Models\GroupReport;
use App\Notifications\Group\SuspendGroupNotification;
use App\Notifications\Group\SuspendGroupMemberNotification;
use App\Services\User\UserService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
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

    public static function reportGroup(array $data)
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



    public static function delete($report_id)
    {
        $report = self::getById($report_id);
        $report->delete();
    }

    public function suspendGroup(Group $group, $reason = null)
    {
        DB::beginTransaction();
        try {
            // Update the group's status to suspended
            $group->update([
                'status' => StatusConstants::SUSPENDED,
            ]);

            // Get the admins of the group
            $admins = $group->members()->where('role', 'Admin')->get();

            // Send notification to the group's admins
            Notification::send($admins, new SuspendGroupNotification($group, $reason, StatusConstants::SUSPENDED));

            DB::commit();
            return 'Group has been suspended.';
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function suspensionLift(Group $group, $reason = null)
    {
        DB::beginTransaction();
        try {
            // Update the group's status to accepted (activated)
            $group->update([
                'status' => StatusConstants::ACTIVE,
            ]);

            // Get the admins of the group
            $admins = $group->members()->where('role', 'Admin')->get();

            // Send notification to the group's admins
            Notification::send($admins, new SuspendGroupNotification($group, $reason, StatusConstants::ACCEPTED));

            DB::commit();
            return 'Group has been activated.';
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }



    public function suspendMember($group_member_report_id)
    {
        // Find the GroupMemberReport by ID
        try {
            $group_member_report = GroupMemberReport::findOrFail($group_member_report_id);
        } catch (ModelNotFoundException $e) {
            throw new Exception('Group member report not found.');
        }

        // Find the group member related to the report
        try {
            $group_member = GroupMember::findOrFail($group_member_report->group_member_id);
        } catch (ModelNotFoundException $e) {
            throw new Exception('Group member not found.');
        }

        DB::beginTransaction();
        try {
            $suspensionDurations = [
                1 => now()->addHours(rand(24, 48)),
                2 => now()->addDays(7),
                3 => now()->addDays(30),
            ];

            // Check if the user is already banned
            if ($group_member->banned) {
                throw new Exception('User is already banned and cannot be suspended.');
            }

            $newSuspensionCount = $group_member->suspension_count + 1;

            // Determine if the user should be banned
            if ($newSuspensionCount > 3) {
                $group_member->update([
                    'banned' => true,
                    'suspension_end' => null,
                    'status' => StatusConstants::BANNED,
                ]);

                Notification::send($group_member->user, new SuspendGroupMemberNotification($group_member->user, 'You have been permanently banned from the group.'));
                DB::commit();
                return 'User has been banned permanently.';
            }

            $suspensionEnd = $suspensionDurations[$newSuspensionCount] ?? now()->addHours(24);

            $group_member->update([
                'suspension_count' => $newSuspensionCount,
                'suspension_end' => $suspensionEnd,
                'status' => StatusConstants::SUSPENDED,
            ]);

            Notification::send($group_member->user, new SuspendGroupMemberNotification($group_member->user, "You have been suspended until {$suspensionEnd->toDateTimeString()} for failing to comply with the group's rules."));

            DB::commit();
            return "User has been suspended until {$suspensionEnd->toDateTimeString()}.";
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }
    public function undoGroupMemberSuspension($group_member_id)
    {
        // Attempt to find the group member
        $group_member = GroupMember::findOrFail($group_member_id);
    
        // Check if the member is currently suspended
        if (!$group_member->suspension_end || $group_member->suspension_end <= now()) {
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, 'User is not currently suspended.');
        }
    
        // Reset suspension end
        $group_member->update([
            'suspension_end' => null,
            'status' => StatusConstants::ACTIVE,
        ]);
    
        // Optionally, send a notification to the user
        Notification::send($group_member->user, new SuspendGroupMemberNotification($group_member->user, 'Your suspension has been lifted. Failure to comply may lead to a longer suspension from the group or banned.'));
    
        return 'Suspension has been lifted.';
    }
    

}
