<?php

namespace App\Services\Report\Group;

use App\Constants\Account\User\UserConstants;
use App\Constants\General\AppConstants;
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
use App\Notifications\Group\UndoGroupSuspensionNotification;
use App\Services\User\UserService;
use Exception;
use Illuminate\Http\Request;
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
            "action" => "required|string|in:Suspended,Activated,Resolved",
            "reason" => "required|string",
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

    public function suspendOrBanGroup(Request $request, $group_id)
    {
        DB::beginTransaction();
        try {
            $group = Group::find($group_id);

            if (empty($group)) {
                throw new ModelNotFoundException('Group not found.');
            }

            $actionType = $request->input('action_type');
            $reason = $request->input('suspension_reason');
            $suspensionDurations = [
                1 => now()->addHours(rand(24)),
                2 => now()->addDays(7),
                3 => now()->addDays(30),
            ];

            if ($actionType === 'ban') {
                // Ensure group is not already banned
                if ($group->status === StatusConstants::BANNED) {
                    return 'Group is already banned.';
                }

                // Update the group's status to banned
                $group->update([
                    'status' => StatusConstants::BANNED,
                ]);

                // Notify group admins
                $user = $group->members()->where('role', UserConstants::OWNER)->first()->user;
                Notification::send($user, new SuspendGroupNotification($group, null, "You have been permanently banned from the group for the following reason: {$reason}."));

                DB::commit();
                return 'Group has been banned permanently.';
            } elseif ($actionType === 'suspend') {
                // Ensure group is not already suspended
                if ($group->status === StatusConstants::SUSPENDED) {
                    return 'Group is already suspended.';
                }

                // Calculate suspension end time
                $suspensionEnd = $suspensionDurations[$request->input('duration')] ?? now()->addHours(24);
                $duration = $suspensionEnd->diffForHumans();

                // Update the group's status to suspended
                $group->update([
                    'status' => StatusConstants::SUSPENDED,
                ]);

                // Notify group admins
                $user = $group->members()->where('role', UserConstants::OWNER)->first()->user;
                Notification::send($user, new SuspendGroupNotification($group, $duration, $reason));

                DB::commit();
                return 'Group has been suspended.';
            } else {
                throw new InvalidRequestException('Invalid action type.');
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }




    public function deleteGroup(Group $group, $reason = null)
    {
        DB::beginTransaction();
        try {
            // Get the admins of the group
            $admins = $group->members()->where('role', UserConstants::OWNER)->first()->user;

            // Delete the group
            $group->delete();

            // Send notification to the group's admins
            // foreach ($admins as $admin) {
            //     Notification::send($admin, new SuspendGroupMemberNotification($group, null));
            // }

            DB::commit();
            return 'Group has been deleted.';
        } catch (\Throwable $e) {
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
            $user = $group->members()->where('role', UserConstants::OWNER)->first()->user;

            // Send notification to the group's admins
            Notification::send($user, new UndoGroupSuspensionNotification($group));

            DB::commit();
            return 'Group has been activated.';
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }


    public function suspendOrBanMember(Request $request, $group_member_report_id)
    {
        DB::beginTransaction();
        try {
            $group_member_report = GroupMemberReport::find($group_member_report_id);

            if (!$group_member_report) {
                throw new ModelNotFoundException('Group member report not found.');
            }

            $group_member = GroupMember::findOrFail($group_member_report->group_member_id);

            if (!$group_member) {
                throw new ModelNotFoundException('Group member not found.');
            }

            $actionType = $request->input('action_type');
            $suspensionReason = $request->input('suspension_reason');
            $suspensionDurations = [
                1 => now()->addHours(rand(24)),
                2 => now()->addDays(7),
                3 => now()->addDays(30),
            ];

            // Check if the user needs to be banned
            if ($group_member->suspension_count >= 3 || $actionType === 'ban') {
                if ($group_member->banned) {
                    DB::commit();
                    return 'User has aready been banned.';
                }

                // Ban the user
                $group_member->update([
                    'banned' => true,
                    'suspension_end' => null,
                    'status' => StatusConstants::BANNED,
                ]);

                Notification::send($group_member->user, new SuspendGroupMemberNotification($group_member, "You have been permanently banned from the group for the following reason: {$suspensionReason}."));

                DB::commit();
                return 'User has been banned permanently.';
            } else {
                // Apply suspension
                $newSuspensionCount = $group_member->suspension_count + 1;
                $suspensionEnd = $suspensionDurations[$request->input('duration')] ?? now()->addHours(24);

                $group_member->update([
                    'suspension_reason' => $suspensionReason,
                    'suspension_count' => $newSuspensionCount,
                    'suspension_end' => $suspensionEnd,
                    'status' => StatusConstants::SUSPENDED,
                ]);

                $message = "You have been suspended until {$suspensionEnd->diffForHumans()} for the following reason: {$suspensionReason}.";

                Notification::send($group_member->user, new SuspendGroupMemberNotification($group_member, $message));

                DB::commit();
                return "User has been suspended until {$suspensionEnd->toDateTimeString()}.";
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }





    public function undoGroupMemberSuspension($group_member_id)
    {
        // Attempt to find the group member
        $group_member = GroupMember::find($group_member_id);

        // Check if the member is currently suspended
        if (!$group_member) {
            throw new InvalidRequestException("User is not currently suspended.");
        }

        // Reset suspension end
        $group_member->update([
            'suspension_end' => null,
            'suspension_reason' => null,
            'status' => StatusConstants::ACTIVE,
        ]);

        // Optionally, send a notification to the user
        Notification::send($group_member->user, new SuspendGroupMemberNotification($group_member, 'Your suspension has been lifted. Failure to comply may lead to a longer suspension from the group or banned.'));

        return 'Suspension has been lifted.';
    }
}
