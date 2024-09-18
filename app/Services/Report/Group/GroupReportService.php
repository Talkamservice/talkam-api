<?php

namespace App\Services\Report\Group;

use App\Constants\Account\User\UserConstants;
use App\Constants\ActivityLog\ActivitiesConstants;
use App\Constants\ActivityLog\ActivityLogConstants;
use App\Constants\General\AppConstants;
use App\Constants\General\NotificationConstants;
use App\Constants\General\StatusConstants;
use App\Events\RefreshNotification;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberReport;
use App\Models\GroupReport;
use App\Notifications\Group\DeleteGroupNotification;
use App\Notifications\Group\SuspendGroupNotification;
use App\Notifications\Group\SuspendGroupMemberNotification;
use App\Notifications\Group\UndoGroupSuspensionNotification;
use App\Services\ActivityLog\ActivityLogService;
use App\Services\User\UserService;
use Carbon\Carbon;
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
            if ($report) {
                $report->group->update([
                    'is_reported' => true,
                ]);
            }
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
            $suspensionDuration = $request->input('duration');

            if ($actionType === 'ban') {
                if ($group->status === StatusConstants::BANNED) {
                    return 'Group is already banned.';
                }

                $group->update(['status' => StatusConstants::BANNED]);

                $user = $group->creator;

                if (!empty($user)) {
                    Notification::send($user, new SuspendGroupNotification($group, null, "Your group has been banned for the following reason: {$reason}."));
                    broadcast(new RefreshNotification($user->id));
                }

                // Log the activity
                (new ActivityLogService)
                    ->setEvent("banned")
                    ->setTitle("Group Banned")
                    ->setDescription((auth()->user()->email) . " banned a group")
                    ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
                    ->setActivity(ActivitiesConstants::GROUP_BANNED)
                    ->setModel(Group::class, $group->id)
                    ->setAdmin(auth()->user()?->id)
                    ->setData(["Group" => $group->refresh()->toArray()])
                    ->setUrl(request()->fullUrl())
                    ->log();

                DB::commit();
                return 'Group has been banned permanently.';
            } elseif ($actionType === 'suspend') {
                if ($group->status === StatusConstants::SUSPENDED) {
                    return 'Group is already suspended.';
                }

                // Calculate the number of suspension days
                $suspensionEnd = Carbon::parse($suspensionDuration); // Convert input date to Carbon
                $now = Carbon::now(); // Current date

                if ($suspensionEnd->lessThanOrEqualTo($now)) {
                    return 'Invalid suspension date. The date must be in the future.';
                }

                $days = $now->diffInDays($suspensionEnd); // Get the number of days between now and the suspension end date
                $duration = "{$days} days"; // Duration in days
                $group->update(['status' => StatusConstants::SUSPENDED]);

                $user = $group->members()->where('role', UserConstants::OWNER)->first()->user;
                Notification::send($user, new SuspendGroupNotification($group, $duration, $reason));
                broadcast(new RefreshNotification($user->id));

                // Log the activity
                (new ActivityLogService)
                    ->setEvent("suspended")
                    ->setTitle("Group Suspended")
                    ->setDescription((auth()->user()->email) . " suspended a group")
                    ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
                    ->setActivity(ActivitiesConstants::GROUP_SUSPENDED)
                    ->setModel(Group::class, $group->id)
                    ->setAdmin(auth()->user()?->id)
                    ->setData(["Group" => $group->refresh()->toArray()])
                    ->setUrl(request()->fullUrl())
                    ->log();

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

    public function deleteGroup($group_id)
    {
        DB::beginTransaction();
        try {
            $group = Group::find($group_id);
            if (empty($group)) {
                throw new ModelNotFoundException('Group not found.');
            }
            $admin = $group->members()->where('role', UserConstants::OWNER)->first()->user;
            $group->delete();
            Notification::send($admin, new DeleteGroupNotification($group));
            broadcast(new RefreshNotification($admin->id));

            // Log the activity
            (new ActivityLogService)
                ->setEvent("deleted")
                ->setTitle("Group Activated")
                ->setDescription((auth()->user()->email) . " deleted a group")
                ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
                ->setActivity(ActivitiesConstants::GROUP_DELETED)
                ->setModel(Group::class, $group->id)
                ->setAdmin(auth()->user()?->id)
                ->setData(["Group" => $group->refresh()->toArray()])
                ->setUrl(request()->fullUrl())
                ->log();

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
            $group->update(['status' => StatusConstants::ACTIVE]);

            $user = $group->members()->where('role', UserConstants::OWNER)->first()->user;

            Notification::send($user, new UndoGroupSuspensionNotification($group));
            broadcast(new RefreshNotification($user->id));

            // Log the activity
            (new ActivityLogService)
                ->setEvent("activated")
                ->setTitle("Group Activated")
                ->setDescription((auth()->user()->email) . " activated a group")
                ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
                ->setActivity(ActivitiesConstants::GROUP_ACTIVATED)
                ->setModel(Group::class, $group->id)
                ->setAdmin(auth()->user()?->id)
                ->setData(["Group" => $group->refresh()->toArray()])
                ->setUrl(request()->fullUrl())
                ->log();

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
            // dd($group_member_report_id);
            if (!$group_member_report) {
                throw new ModelNotFoundException('Group member report not found.');
            }

            $group_member = GroupMember::findOrFail($group_member_report->group_member_id);

            if (!$group_member) {
                throw new ModelNotFoundException('Group member not found.');
            }

            $actionType = $request->input('action_type');
            $suspensionReason = $request->input('suspension_reason');
            $suspensionDuration = $request->input('duration'); // Date input for suspension end


            $newSuspensionCount = $group_member->suspension_count + 1;

            // Calculate the suspension end date from the input duration
            $suspensionEnd = Carbon::parse($suspensionDuration); // Convert input date to Carbon
            $now = Carbon::now();

            if ($suspensionEnd->lessThanOrEqualTo($now)) {
                return 'Invalid suspension date. The date must be in the future.';
            }

            $days = $now->diffInDays($suspensionEnd); // Calculate the number of days

            $group_member->update([
                'suspension_reason' => $suspensionReason,
                'suspension_count' => $newSuspensionCount,
                'suspension_end' => $suspensionEnd,
                'status' => StatusConstants::SUSPENDED,
            ]);

            $message = "You have been suspended for {$days} days until {$suspensionEnd->toFormattedDateString()} for the following reason: {$suspensionReason}.";

            Notification::send($group_member->user, new SuspendGroupMemberNotification($group_member, $message));
            broadcast(new RefreshNotification($group_member->user_id));

            // Log the activity
            (new ActivityLogService)
                ->setEvent("suspended")
                ->setTitle("Group Member Suspended")
                ->setDescription((auth()->user()->email) . " suspended a group member")
                ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
                ->setActivity(ActivitiesConstants::GROUP_MEMBER_SUSPENDED)
                ->setModel(GroupMember::class, $group_member->id)
                ->setAdmin(auth()->user()?->id)
                ->setData(["Group Member" => $group_member->refresh()->toArray()])
                ->setUrl(request()->fullUrl())
                ->log();

            DB::commit();
            return 'Group member has been suspended for ' . $days . ' days.';
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
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
        broadcast(new RefreshNotification($group_member->user_id));

        (new ActivityLogService)
            ->setEvent("activated")
            ->setTitle("Group Member Activated")
            ->setDescription((auth()->user()->email) . " activated a group member")
            ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
            ->setActivity(ActivitiesConstants::GROUP_MEMBER_ACTIVATED)
            ->setModel(GroupMember::class, $group_member->id)
            ->setAdmin(auth()->user()?->id)
            ->setData(
                ["Group" => $group_member->refresh()->toArray()]
            )
            ->setUrl(request()->fullUrl())
            ->log();
        return 'Suspension has been lifted.';
    }
}
