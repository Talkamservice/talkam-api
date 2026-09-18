<?php

namespace App\Services\User;

use App\Constants\General\StatusConstants;
use App\Jobs\ProcessDataExportJob;
use App\Models\DataExportRequest;
use App\Models\User;

class DataExportService
{
    /**
     * Queue a fresh export; only one pending request at a time.
     */
    public function request(User $user): DataExportRequest
    {
        $pending = DataExportRequest::where('user_id', $user->id)
            ->where('status', StatusConstants::PENDING)
            ->first();

        if (!empty($pending)) {
            return $pending;
        }

        $export = DataExportRequest::create([
            'user_id' => $user->id,
            'status' => StatusConstants::PENDING,
        ]);

        ProcessDataExportJob::dispatch($export->id);

        return $export;
    }

    /**
     * Latest request state; the signed link is only served while unexpired.
     */
    public static function latest(User $user): ?array
    {
        $export = DataExportRequest::with('file')
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        if (empty($export)) {
            return null;
        }

        $link_available = $export->status == StatusConstants::COMPLETED
            && !empty($export->file)
            && !empty($export->expires_at)
            && $export->expires_at->isFuture();

        return [
            'id' => $export->id,
            'status' => $export->status,
            'expires_at' => $export->expires_at?->toDateTimeString(),
            'download_url' => $link_available ? $export->file->url() : null,
        ];
    }
}
