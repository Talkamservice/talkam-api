<?php

namespace App\Console\Commands;

use App\Constants\ActivityLog\ActivitiesConstants;
use App\Constants\ActivityLog\ActivityLogConstants;
use App\Services\ActivityLog\ActivityLogService;
use App\Services\Notifications\FirebaseNotificationService;
use Illuminate\Console\Command;

class TestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        (new FirebaseNotificationService)
            ->setTitle("Test notification")
            ->setBody("Message")
            ->setType("Test")
            ->byUserId(11)
            ->setMetadata([
                "type" => "conversation",
            ])
            ->initiate();

        // (new ActivityLogService)
        //     ->setEvent("deleted")
        //     ->setTitle("Client Removal")
        //     ->setDescription((auth("admin")->user()?->name . " deleted a client"))
        //     ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
        //     ->setActivity(ActivitiesConstants::DELETED_CLIENT)
        //     ->setModel(User::class, $user->id)
        //     ->setAdmin(auth("admin")->user()->id)
        //     ->setData([
        //         "client" => $user->refresh()->toArray(),
        //     ])
        //     ->setUrl(request()->fullUrl())
        //     ->log();
    }
}
