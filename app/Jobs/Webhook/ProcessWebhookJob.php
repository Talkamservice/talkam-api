<?php

namespace App\Jobs\Webhook;

use App\Constants\Finance\Payment\PaymentConstants;
use App\Services\Jobs\Webhook\FlutterwaveWebhookJobService;
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
        if ($this->data["source"] == PaymentConstants::FLUTTERWAVE) {
            (new FlutterwaveWebhookJobService($this->data))->process();
        }
    }

    public function middleware()
    {
        return [(new WithoutOverlapping($this->user_id))->releaseAfter(60)];
    }
}
