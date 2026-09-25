<?php

namespace App\Jobs;

use App\Models\PlatformSetting;
use App\Services\PlatformAdmin\PlatformPayoutService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Platform Admin "Process All" — runs PlatformPayoutService::processAll()
 * (the same per-therapist PayoutService::withdraw() loop) off the request
 * so a batch of outbound Flutterwave transfers can't time out an HTTP
 * request. Result is stashed in PlatformSetting (same key/value store
 * already used elsewhere) since a page reading "how did the last run go"
 * doesn't need its own table.
 */
class ProcessAllPayoutsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const SETTING_KEY = "last_payout_run";

    public $tries = 1;

    public function __construct(public ?int $triggeredBy = null)
    {
    }

    public function handle(): void
    {
        PlatformSetting::set(self::SETTING_KEY, json_encode([
            "status" => "running",
            "started_at" => now()->toDateTimeString(),
            "triggered_by" => $this->triggeredBy,
        ]));

        $result = PlatformPayoutService::processAll();

        PlatformSetting::set(self::SETTING_KEY, json_encode([
            "status" => "completed",
            "started_at" => now()->toDateTimeString(),
            "finished_at" => now()->toDateTimeString(),
            "triggered_by" => $this->triggeredBy,
            "processed" => $result["processed"],
            "failed" => $result["failed"],
            "errors" => $result["errors"],
        ]));
    }
}
