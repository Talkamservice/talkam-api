<?php

namespace App\Jobs;

use App\Services\General\Guzzle\GuzzleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FirebaseMessagingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $params;
    public $post_data;
    public $url;
    public $headers;
    public $configs;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $params)
    {
        $this->params = $params;
        $this->post_data = $params["post_data"];
        $this->url = $params["url"];
        $this->headers = $params["headers"];
        $this->configs = $params["configs"];
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            foreach ($this->post_data as $key => $data) {
                $service = new GuzzleService($this->headers);
                $response = $service->post($this->url, array_merge($data, $this->configs));
                logger("Firebase notification response", [$response]);
            }
        } catch (\Exception $e) {
            logger($e->getMessage(), $e->getTrace());
            // throw $e;
        }
    }
}
