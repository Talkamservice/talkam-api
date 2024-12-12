<?php

namespace App\Console\Commands\Post;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PostStatsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:post-stats-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to calculate and store post stats every 6 hours';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $endDate = now();
        $startDate = $endDate->clone()->subHours(6);
        $postIds = DB::table('post_stat_logs')->distinct()->pluck('post_id');
        foreach ($postIds as $postId) {
            $aggregatedStats = DB::table('post_stat_logs')
                ->selectRaw("
                    AVG(daily_impressions) as avg_impressions_per_day, 
                    AVG(daily_time_spent) as avg_time_spent_per_day
                ")
                ->fromSub(function ($query) use ($postId, $startDate, $endDate) {
                    $query->from('post_stat_logs')
                        ->selectRaw("
                            DATE(logged_at) as day, 
                            SUM(impressions) as daily_impressions, 
                            SUM(time_spent) as daily_time_spent
                        ")
                        ->where('post_id', $postId)
                        ->whereBetween('logged_at', [$startDate, $endDate])
                        ->groupBy(DB::raw("DATE(logged_at)"));
                }, 'daily_stats')
                ->first();
                Log::info((array) $aggregatedStats);
                
            if ($aggregatedStats) {
                DB::table('post_performances')->updateOrInsert(
                    ['post_id' => $postId],
                    [
                        'avg_impressions_per_day' => $aggregatedStats->avg_impressions_per_day ?? 0,
                        'avg_time_spent_per_day' => $aggregatedStats->avg_time_spent_per_day ?? 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
        $this->info('Post stats calculated and updated successfully.');
    }
}
