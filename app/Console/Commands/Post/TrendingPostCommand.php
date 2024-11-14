<?php

namespace App\Console\Commands\Post;

use App\Services\Post\PostEventService;
use Illuminate\Console\Command;

class TrendingPostCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:trending_tags_handle';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the trending tags';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        PostEventService::generalTrendingTags();
        PostEventService::categoryTrendingTags();
    }
}
