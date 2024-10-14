<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\Api\V1\User\Group\GroupMemberController;

class TestGroupMemberRemoval extends Command
{
    protected $signature = 'test:remove-group-member {memberId}';
    protected $description = 'Test removing a group member and sending notification';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $memberId = $this->argument('memberId');
        
        try {
            // Resolve the controller from the container
            $controller = app(GroupMemberController::class);
            // Call the destroy method
            $response = $controller->destroy($memberId);

            // Output the response
            $this->info($response->getContent());
        } catch (\Exception $e) {
            // Output any exceptions
            $this->error('Error: ' . $e->getMessage());
        }
    }
}
