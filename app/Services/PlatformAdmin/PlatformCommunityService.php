<?php

namespace App\Services\PlatformAdmin;

use App\Constants\Account\User\UserConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\CommentReport;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberReport;
use App\Models\GroupReport;
use App\Models\PostCategory;
use App\Models\PostReport;
use App\Models\User;
use App\Services\Group\GroupMemberService;
use App\Services\Group\GroupService;
use App\Services\Report\Group\GroupReportService;
use Illuminate\Http\Request;

/**
 * Platform-admin wrapper over the real moderation queues (PostReport/
 * CommentReport/GroupReport) and, for groups, the real suspend/ban/delete
 * surface in GroupReportService — the Blade admin panel's only consumer
 * until now. Post/comment reports already had a resolve+delete action here;
 * group reports were read-only, this closes that gap using the exact same
 * service the Blade "Groups" moderation queue already relies on.
 */
class PlatformCommunityService
{
    public static function overview(): array
    {
        $pendingCount = fn (string $model) => $model::where('status', StatusConstants::PENDING)->count();
        $resolvedThisWeek = fn (string $model) => $model::where('status', StatusConstants::RESOLVED)
            ->whereBetween('updated_at', [now()->startOfWeek(), now()])
            ->count();

        return [
            "open_reports" => $pendingCount(PostReport::class) + $pendingCount(CommentReport::class) + $pendingCount(GroupReport::class) + $pendingCount(GroupMemberReport::class),
            "resolved_this_week" => $resolvedThisWeek(PostReport::class) + $resolvedThisWeek(CommentReport::class) + $resolvedThisWeek(GroupReport::class) + $resolvedThisWeek(GroupMemberReport::class),
            "total_groups" => Group::count(),
            "active_groups" => Group::where('status', StatusConstants::ACTIVE)->count(),
            // Distinct groups with a still-open report — more reliable than
            // `groups.is_reported`, which is only ever set at report-creation
            // time (App\Services\Report\Group\GroupReportService::reportGroup)
            // and can drift stale relative to the reports table.
            "flagged_groups" => (int) GroupReport::where('status', StatusConstants::PENDING)
                ->selectRaw('COUNT(DISTINCT group_id) as c')
                ->value('c'),
            "suspended_groups" => Group::whereIn('status', [StatusConstants::SUSPENDED, StatusConstants::BANNED])->count(),
        ];
    }

    public static function postReports(int $page, int $per_page = 20)
    {
        return PostReport::with(['user:id,first_name,last_name', 'post:id,title,body,user_id,status'])
            ->latest()
            ->paginate($per_page, ['*'], 'page', $page);
    }

    public static function commentReports(int $page, int $per_page = 20)
    {
        return CommentReport::with(['user:id,first_name,last_name', 'comment:id,comment,user_id,post_id'])
            ->latest()
            ->paginate($per_page, ['*'], 'page', $page);
    }

    public static function groupReports(int $page, int $per_page = 20)
    {
        $reports = GroupReport::with(['user:id,first_name,last_name', 'group:id,name,status,description,created_by'])
            ->latest()
            ->paginate($per_page, ['*'], 'page', $page);

        $groupIds = $reports->getCollection()->pluck('group_id')->filter()->unique();
        $memberCounts = GroupMember::whereIn('group_id', $groupIds)
            ->selectRaw('group_id, COUNT(*) as c')
            ->groupBy('group_id')
            ->pluck('c', 'group_id');
        $openReportCounts = GroupReport::whereIn('group_id', $groupIds)
            ->where('status', StatusConstants::PENDING)
            ->selectRaw('group_id, COUNT(*) as c')
            ->groupBy('group_id')
            ->pluck('c', 'group_id');

        $reports->getCollection()->transform(function (GroupReport $r) use ($memberCounts, $openReportCounts) {
            $row = $r->toArray();
            $row['group']['member_count'] = $memberCounts[$r->group_id] ?? 0;
            $row['group']['open_report_count'] = $openReportCounts[$r->group_id] ?? 0;
            return $row;
        });

        return $reports;
    }

    public static function resolveGroupReport(int $id): GroupReport
    {
        $report = self::findGroupReport($id);
        if ($report->status === StatusConstants::RESOLVED) {
            throw new InvalidRequestException("This report is already resolved.");
        }
        $report->update(['status' => StatusConstants::RESOLVED]);
        return $report->refresh();
    }

    public static function suspendGroup(int $groupId, string $reason, string $suspendUntil): string
    {
        $request = new Request(['action_type' => 'suspend', 'suspension_reason' => $reason, 'duration' => $suspendUntil]);
        return (new GroupReportService)->suspendOrBanGroup($request, $groupId);
    }

    public static function banGroup(int $groupId, string $reason): string
    {
        $request = new Request(['action_type' => 'ban', 'suspension_reason' => $reason]);
        return (new GroupReportService)->suspendOrBanGroup($request, $groupId);
    }

    public static function reactivateGroup(int $groupId): string
    {
        $group = Group::find($groupId);
        if (empty($group)) {
            throw new ModelNotFoundException("Group not found.");
        }
        return (new GroupReportService)->suspensionLift($group);
    }

    public static function deleteGroup(int $groupId): string
    {
        return (new GroupReportService)->deleteGroup($groupId);
    }

    public static function categories()
    {
        return PostCategory::orderBy('name')->get(['id', 'name']);
    }

    /** Every group on the platform, not just reported ones — GroupService::
     *  list() already supports search/status/category filters, reused as-is. */
    public static function allGroups(array $filters, int $page, int $per_page = 20)
    {
        $groups = GroupService::list($filters)
            ->withCount('members')
            ->with('category:id,name')
            ->latest()
            ->paginate($per_page, ['*'], 'page', $page);

        $groupIds = $groups->getCollection()->pluck('id');
        $openReportCounts = GroupReport::whereIn('group_id', $groupIds)
            ->where('status', StatusConstants::PENDING)
            ->selectRaw('group_id, COUNT(*) as c')
            ->groupBy('group_id')
            ->pluck('c', 'group_id');

        $groups->getCollection()->transform(function (Group $g) use ($openReportCounts) {
            $row = $g->toArray();
            $row['open_report_count'] = $openReportCounts[$g->id] ?? 0;
            return $row;
        });

        return $groups;
    }

    public static function updateGroup(int $groupId, array $data): Group
    {
        return (new GroupService)->update($data, $groupId);
    }

    public static function groupDetail(int $groupId): array
    {
        $group = Group::with(['creator:id,first_name,last_name,email', 'category:id,name'])
            ->withCount('members')
            ->find($groupId);

        if (empty($group)) {
            throw new ModelNotFoundException("Group not found.");
        }

        $row = $group->toArray();
        $row['open_report_count'] = GroupReport::where('group_id', $groupId)->where('status', StatusConstants::PENDING)->count();
        $row['open_comment_report_count'] = CommentReport::whereHas('post', fn ($q) => $q->where('group_id', $groupId))
            ->where('status', StatusConstants::PENDING)->count();

        return $row;
    }

    /** Group Reports (reports against the group itself), scoped to one group
     *  — the Group Detail page's counterpart to the platform-wide queue. */
    public static function groupReportsForGroup(int $groupId, int $page, int $per_page = 20)
    {
        return GroupReport::where('group_id', $groupId)
            ->with(['user:id,first_name,last_name'])
            ->latest()
            ->paginate($per_page, ['*'], 'page', $page);
    }

    /** Comment Reports whose comment sits on a post inside this group —
     *  CommentReport carries its own post_id, so no join through PostComment
     *  is needed. */
    public static function commentReportsForGroup(int $groupId, int $page, int $per_page = 20)
    {
        return CommentReport::whereHas('post', fn ($q) => $q->where('group_id', $groupId))
            ->with(['user:id,first_name,last_name', 'comment:id,comment,user_id,post_id'])
            ->latest()
            ->paginate($per_page, ['*'], 'page', $page);
    }

    /** Real create — the same GroupService::create() the app itself uses for
     *  user-initiated groups, just with the admin picking the real user who
     *  becomes Owner instead of the caller. There's no "TalkAM-owned" group
     *  concept in the schema — every group needs a real owner member row. */
    public static function createGroup(array $data): Group
    {
        $owner = User::where('email', $data['owner_email'])->first();
        if (empty($owner)) {
            throw new ModelNotFoundException("No user found with that email — a group needs a real owner.");
        }

        return (new GroupService)->setUser($owner)->create([
            "category_id" => $data['category_id'],
            "name" => $data['name'],
            "description" => $data['description'] ?? null,
            "group_access" => $data['group_access'] ?? 'Opened',
        ]);
    }

    public static function groupMembers(int $groupId, int $page, int $per_page = 20)
    {
        return GroupMember::where('group_id', $groupId)
            ->with('user:id,first_name,last_name,email')
            ->orderByRaw("FIELD(role, 'Owner','Admin','Community Manager','Moderator','Member')")
            ->latest('created_at')
            ->paginate($per_page, ['*'], 'page', $page);
    }

    public static function updateGroupMemberRole(int $memberId, string $role): GroupMember
    {
        if (!in_array($role, UserConstants::ADMIN_ASSIGNABLE_GROUP_ROLES, true)) {
            throw new InvalidRequestException("Invalid role.");
        }

        $member = GroupMember::find($memberId);
        if (empty($member)) {
            throw new ModelNotFoundException("Member not found.");
        }
        if ($member->role === UserConstants::OWNER) {
            throw new InvalidRequestException("The group owner's role can't be changed here — that's a separate ownership transfer.");
        }

        return (new GroupMemberService)->update(['role' => $role], $memberId);
    }

    /** Toggles suspend/unsuspend — reuses GroupMemberService::suspendMember(),
     *  already real and used by the app's own suspend flow; the platform-admin
     *  API just didn't expose it until now. */
    public static function suspendGroupMember(int $memberId): GroupMember
    {
        return (new GroupMemberService)->suspendMember($memberId);
    }

    public static function addGroupMember(int $groupId, string $email, string $role): GroupMember
    {
        $user = User::where('email', $email)->first();
        if (empty($user)) {
            throw new ModelNotFoundException("No user found with that email.");
        }
        if (!in_array($role, UserConstants::ADMIN_ASSIGNABLE_GROUP_ROLES, true)) {
            throw new InvalidRequestException("Invalid role.");
        }

        $existing = GroupMember::where('group_id', $groupId)->where('user_id', $user->id)->first();
        if (!empty($existing)) {
            throw new InvalidRequestException("This user is already a member of the group.");
        }

        return GroupMemberService::addNewAdmin([
            "group_id" => $groupId,
            "user_id" => $user->id,
            "role" => $role,
        ]);
    }

    /** Reuses GroupMemberService::removeByUserId(), which already protects
     *  the Owner from removal and keeps group_follows in sync. */
    public static function removeGroupMember(int $memberId): void
    {
        $member = GroupMember::find($memberId);
        if (empty($member)) {
            throw new ModelNotFoundException("Member not found.");
        }
        GroupMemberService::removeByUserId([
            "group_id" => $member->group_id,
            "user_id" => $member->user_id,
        ]);
    }

    public static function groupMemberReports(int $page, int $per_page = 20)
    {
        return GroupMemberReport::with([
            'user:id,first_name,last_name',
            'groupMember:id,group_id,user_id,role,status',
            'groupMember.user:id,first_name,last_name',
            'groupMember.group:id,name',
        ])
            ->latest()
            ->paginate($per_page, ['*'], 'page', $page);
    }

    public static function resolveGroupMemberReport(int $id): GroupMemberReport
    {
        $report = GroupMemberReport::find($id);
        if (empty($report)) {
            throw new ModelNotFoundException("Report not found.");
        }
        if ($report->status === StatusConstants::RESOLVED) {
            throw new InvalidRequestException("This report is already resolved.");
        }
        $report->update(['status' => StatusConstants::RESOLVED]);
        return $report->refresh();
    }

    public static function suspendOrBanReportedMember(int $reportId, string $reason, string $suspendUntil): string
    {
        $request = new Request(['action_type' => 'suspend', 'suspension_reason' => $reason, 'duration' => $suspendUntil]);
        return (new GroupReportService)->suspendOrBanMember($request, $reportId);
    }

    public static function unsuspendReportedMember(int $groupMemberId): string
    {
        return (new GroupReportService)->undoGroupMemberSuspension($groupMemberId);
    }

    private static function findGroupReport(int $id): GroupReport
    {
        $report = GroupReport::find($id);
        if (empty($report)) {
            throw new ModelNotFoundException("Report not found.");
        }
        return $report;
    }
}
