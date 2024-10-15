<?php

namespace App\Jobs\Webhook;

use App\Constants\Finance\PaymentConstants;
use App\Services\Jobs\Webhook\SafeHavenWebhookJobService;
use App\Services\Jobs\Webhook\SquadWebhookJobService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class ProcessWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(public $data, public $user_id)
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->data["source"] == PaymentConstants::GATEWAY_SQUAD) {
            (new SquadWebhookJobService($this->data))->process();
        } elseif ($this->data["source"] == PaymentConstants::GATEWAY_SAFE_HAVEN) {
            (new SafeHavenWebhookJobService($this->data))->process();
        }
    }

    public function middleware()
    {
        return [(new WithoutOverlapping($this->user_id))->releaseAfter(60)];
    }
}
