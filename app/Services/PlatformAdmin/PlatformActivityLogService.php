<?php

namespace App\Services\PlatformAdmin;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

/**
 * ActivityLogService::list() takes no args and applies no filtering despite
 * the Blade controller passing $request->all() into it (silently discarded —
 * see app/Http/Controllers/Admin/ActivityLog/ActivityLogController.php).
 * This applies the filters for real instead of inheriting that bug.
 *
 * Note: ActivityLog::admin_id actually stores a users.id (every call site
 * is setAdmin(auth()->user()?->id)) — the model's admin() relation points
 * at the wrong (Admin) model; its sibling user() relation on the same FK
 * is the one that resolves correctly, so that's what this eager-loads.
 */
class PlatformActivityLogService
{
    public static function list(Request $request, int $per_page = 30)
    {
        $query = ActivityLog::with('user:id,first_name,last_name,email')->latest();

        if (!empty($search = $request->input('search'))) {
            $query->whereRaw("CONCAT(title, ' ', description) LIKE ?", ["%$search%"]);
        }

        if (!empty($event = $request->input('event'))) {
            $query->where('event', $event);
        }

        if (!empty($type = $request->input('type'))) {
            $query->where('type', $type);
        }

        if (!empty($model = $request->input('model'))) {
            $query->where('model', $model);
        }

        if (!empty($modelId = $request->input('model_id'))) {
            $query->where('model_id', $modelId);
        }

        if (!empty($from = $request->input('from')) && empty($request->input('to'))) {
            $query->whereDate('created_at', '>=', $from);
        }

        if (empty($request->input('from')) && !empty($to = $request->input('to'))) {
            $query->whereDate('created_at', '<=', $to);
        }

        if (!empty($from = $request->input('from')) && !empty($to = $request->input('to'))) {
            $query->whereBetween('created_at', [$from, $to]);
        }

        return $query->paginate($per_page);
    }

    /** One user's event log — every UserService call site logs
     *  setModel(User::class, $user->id), confirmed consistent across
     *  create/update/suspend/ban/strike/delete/etc. */
    public static function forUser(int $userId, int $per_page = 20)
    {
        return ActivityLog::with('user:id,first_name,last_name,email')
            ->where('model', \App\Models\User::class)
            ->where('model_id', $userId)
            ->latest()
            ->paginate($per_page);
    }
}
