<?php

namespace App\Jobs;

use App\Constants\General\StatusConstants;
use App\Models\DataExportRequest;
use App\Services\Media\FileService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;

/**
 * Compiles the user's data into a JSON archive, stores it privately and
 * stamps the config-driven expiry (link purged afterwards).
 */
class ProcessDataExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $export_id)
    {
    }

    public function handle(): void
    {
        $export = DataExportRequest::with('user')->find($this->export_id);
        if (empty($export) || $export->status != StatusConstants::PENDING) {
            return;
        }

        $user = $export->user;

        $payload = [
            'profile' => $user->only([
                'id', 'first_name', 'middle_name', 'last_name', 'email',
                'username', 'phone_number', 'bio', 'gender', 'date_of_birth', 'created_at',
            ]),
            'posts' => $user->posts()->get()->toArray(),
            'consents' => $user->consents()->get()->toArray(),
            'sessions' => \App\Models\TherapySession::where('user_id', $user->id)->get()->toArray(),
            'payments' => \App\Models\Payment::where('user_id', $user->id)->get()->toArray(),
            'generated_at' => now()->toDateTimeString(),
        ];

        $tmp_path = storage_path('app/tmp-export-' . $export->id . '.json');
        File::put($tmp_path, json_encode($payload, JSON_PRETTY_PRINT));

        $file = (new FileService)->save($tmp_path, 'data-exports', null, $user->id);

        $export->update([
            'status' => StatusConstants::COMPLETED,
            'file_id' => $file->id,
            'expires_at' => now()->addDays(config('v2.data_export.expiry_days')),
        ]);
    }
}
