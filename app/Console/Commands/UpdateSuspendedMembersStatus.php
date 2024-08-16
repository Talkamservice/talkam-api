<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\GroupMember;
use Carbon\Carbon;

class UpdateSuspendedMembersStatus extends Command
{
    protected $signature = 'members:update-status';
    protected $description = 'Update the status of suspended group members whose suspension period has ended';

    public function handle()
    {
        $now = Carbon::now();

        // Find members whose suspension is ending or ended
        $suspendedMembers = GroupMember::where('suspension_end', '<=', $now)
            ->where('suspension_count', '>', 0)
            ->where('banned', false)
            ->get();

        foreach ($suspendedMembers as $member) {
            $member->update([
                'suspension_end' => null,
                'status' => 'Active', // Update status to active
            ]);

            // Optional: Notify or log that suspension has ended
        }

        $this->info('Suspended members status updated successfully.');
    }
}
