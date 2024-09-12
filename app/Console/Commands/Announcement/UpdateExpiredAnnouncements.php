<?php

namespace App\Console\Commands\Announcement;

use App\Constants\General\StatusConstants;
use App\Models\Announcement;
use Illuminate\Console\Command;

class UpdateExpiredAnnouncements extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'announcements:update-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update announcements that have expired';


    /**
     * Execute the console command.
     */
    public function handle()
    {
        $announcements = Announcement::where('expired_at', '<', now())
            ->where('status', StatusConstants::ACTIVE)
            ->get();

        foreach ($announcements as $announcement) {
            $announcement->update(['status' => StatusConstants::INACTIVE]);
        }

        $this->info('Expired announcements have been updated.');
    }
}
