<?php

namespace App\Jobs;

use App\Services\Post\PostStatsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PostStatsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public array $data, public $remove, public $user = null)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->remove) {
            (new PostStatsService)->remove($this->data, $this->user);
        } else {
            (new PostStatsService)->create($this->data, $this->user);
        }
    }
}
