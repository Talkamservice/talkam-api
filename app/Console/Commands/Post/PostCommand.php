<?php

namespace App\Console\Commands\Post;

use App\Services\Post\PostEventService;
use Illuminate\Console\Command;

class PostCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:post_handle';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Handle all post related commands';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        PostEventService::publishScheduledPosts();
    }
}
