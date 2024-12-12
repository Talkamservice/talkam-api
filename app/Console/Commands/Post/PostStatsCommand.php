<?php

namespace App\Console\Commands\Post;

use App\Models\PostPerformance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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
    protected $description = 'Command to calculate and store post and group stats every 6 hours';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->withoutPostId();
        $this->withGroupId();
        $this->withPostId();
    }

    public function withoutPostId()
    {
        $stats = DB::table('post_stat_logs')
            ->whereNull("group_id")
                    ->selectRaw("
                AVG(daily_impressions) as avg_impressions_per_day, 
                AVG(daily_time_spent) as avg_time_spent_per_day
            ")
            ->fromSub(function ($query) {
                $query->from('post_stat_logs')
                    ->selectRaw("
                DATE(logged_at) as day, 
                SUM(impressions) as daily_impressions, 
                SUM(time_spent) as daily_time_spent,
                group_id
            ")
                    ->groupBy(DB::raw("DATE(logged_at), group_id"));  // Also group by post_id and group_id
            }, 'daily_stats')->first();

        if ($stats) {
            DB::table('post_performances')
                ->whereNull("post_id")
                ->whereNull("group_id")
                ->delete();

            PostPerformance::create([
                'avg_impressions_per_day' => $stats->avg_impressions_per_day ?? 0,
                'avg_time_spent_per_day' => $stats->avg_time_spent_per_day ?? 0,
            ]);
        }
    }

    public function withPostId()
    {
        $post_ids = DB::table('post_stat_logs')->distinct()->pluck('post_id');

        foreach ($post_ids as $post_id) {
            $stats = DB::table('post_stat_logs')
                ->selectRaw("
                    AVG(daily_impressions) as avg_impressions_per_day, 
                    AVG(daily_time_spent) as avg_time_spent_per_day
                ")
                ->fromSub(function ($query) use ($post_id) {
                    $query->from('post_stat_logs')
                        ->selectRaw("
                            DATE(logged_at) as day, 
                            SUM(impressions) as daily_impressions, 
                            SUM(time_spent) as daily_time_spent
                        ")
                        ->where("post_id", $post_id)
                        ->groupBy(DB::raw("DATE(logged_at)"));
                }, 'daily_stats')
                ->first();

            if ($stats) {
                PostPerformance::updateOrCreate([
                    "post_id" => $post_id,
                ], [
                    'avg_impressions_per_day' => $stats->avg_impressions_per_day ?? 0,
                    'avg_time_spent_per_day' => $stats->avg_time_spent_per_day ?? 0,
                ]);
            }
        }
    }

    public function withGroupId()
    {
        $group_ids = DB::table('post_stat_logs')->distinct()->pluck('group_id');

        foreach ($group_ids as $group_id) {
            $stats = DB::table('post_stat_logs')
                ->selectRaw("
                    AVG(daily_impressions) as avg_impressions_per_day, 
                    AVG(daily_time_spent) as avg_time_spent_per_day
                ")
                ->fromSub(function ($query) use ($group_id) {
                    $query->from('post_stat_logs')
                        ->selectRaw("
                            DATE(logged_at) as day, 
                            SUM(impressions) as daily_impressions, 
                            SUM(time_spent) as daily_time_spent
                        ")
                        ->where("group_id", $group_id)
                        ->groupBy(DB::raw("DATE(logged_at)"));
                }, 'daily_stats')
                ->first();

            if ($stats) {
                PostPerformance::updateOrCreate([
                    "group_id" => $group_id,
                ], [
                    'avg_impressions_per_day' => $stats->avg_impressions_per_day ?? 0,
                    'avg_time_spent_per_day' => $stats->avg_time_spent_per_day ?? 0,
                ]);
            }
        }
    }
}
