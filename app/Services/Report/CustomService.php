<?php

namespace App\Services\Report;

use App\Models\PostReport;
use App\Models\CommentReport;
use App\Models\GroupMemberReport;
use App\Models\GroupReport;

class CustomService
{
    public static function listPostReports(array $data = [])
    {
        $query = PostReport::with(["user", "post"]);

        // Apply search filters
        if (!empty($key = $data["search"] ?? null)) {
            $query->where(function ($query) use ($key) {
                $query->where("reason", "LIKE", "%$key%")
                    ->orWhere("status", "LIKE", "%$key%");
            });
        }

        // Apply date filters
        if (!empty($date = $data["date"] ?? null)) {
            $query->whereDate('created_at', $date);
        }
        return $query;
    }

    public static function listCommentReports(array $data = [])
    {
        $query = CommentReport::with(["user", "comment"]);

        // Apply search filters
        if (!empty($key = $data["search"] ?? null)) {
            $query->where(function ($query) use ($key) {
                $query->where("reason", "LIKE", "%$key%")
                    ->orWhere("status", "LIKE", "%$key%");
            });
        }

        // Apply date filters
        if (!empty($date = $data["date"] ?? null)) {
            $query->whereDate('created_at', $date);
        }

        return $query;
    }

    public static function listGroupReports(array $data = [])
{
    $query = GroupReport::with(["user", "group"]);

    // Apply search filters
    if (!empty($key = $data["search"] ?? null)) {
        $query->where(function ($query) use ($key) {
            $query->where("reason", "LIKE", "%$key%")
                ->orWhere("status", "LIKE", "%$key%")
                ->orWhereHas('group', function ($query) use ($key) {
                    $query->where('name', 'LIKE', "%$key%")
                    ->orWhere('status', 'LIKE', "%$key%");
                });
        });
    }

    // Apply date filters
    if (!empty($date = $data["date"] ?? null)) {
        $query->whereDate('created_at', $date);
    }

    return $query;
}


    public static function listGroupMemberReports(array $data = [])
    {
        $query = GroupMemberReport::with(["user", "groupMember", "groupMember.user"]);

        // Apply search filters
        if (!empty($key = $data["search"] ?? null)) {
            $query->where(function ($query) use ($key) {
                $query->where("reason", "LIKE", "%$key%")
                    ->orWhere("status", "LIKE", "%$key%")
                    ->orWhereHas('groupMember', function ($query) use ($key) {
                        $query->where('suspension_count', 'LIKE', "%$key%")
                        ->orWhereHas('user', function ($query) use ($key) {
                            $query->where('username', 'LIKE', "%$key%")
                                  ->orWhere('first_name', 'LIKE', "%$key%")
                                  ->orWhere('last_name', 'LIKE', "%$key%")
                                  ->orWhere('status', 'LIKE', "%$key%");
                        });
                    });
            });
        }
        

        // Apply date filters
        if (!empty($date = $data["date"] ?? null)) {
            $query->whereDate('created_at', $date);
        }

        return $query;
    }

    public static function listCombinedReports(array $data = [])
    {
        $post_reports = self::listPostReports($data)->get();
        $comment_reports = self::listCommentReports($data)->get();
        $group_reports = self::listGroupReports($data)->get();
        $group_member_reports = self::listGroupMemberReports($data)->get();

        // Merge all reports and sort if needed
        return $post_reports->merge($comment_reports)
            ->merge($group_reports)
            ->merge($group_member_reports);
    }
}
