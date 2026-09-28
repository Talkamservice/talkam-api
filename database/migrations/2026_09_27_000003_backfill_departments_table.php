<?php

use App\Models\Department;
use App\Models\Invitation;
use App\Models\OrganizationMember;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-time backfill: turns the free-text `department` strings already sitting
 * on organization_members/invitations into real Department rows (scoped by
 * organization_id, per the new departments table), then points each row's
 * new department_id at the right one. The old string columns are dropped in
 * the migration right after this — this one only needs to run once, before
 * that drop, so both tables' string values survive into the new FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        $names = OrganizationMember::query()
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->select('organization_id', 'department')
            ->distinct()
            ->get()
            ->concat(
                Invitation::query()
                    ->whereNotNull('organization_id')
                    ->whereNotNull('department')
                    ->where('department', '!=', '')
                    ->select('organization_id', 'department')
                    ->distinct()
                    ->get()
            );

        $now = now();

        foreach ($names->unique(fn ($row) => $row->organization_id . '|' . trim($row->department)) as $row) {
            $name = trim($row->department);

            if ($name === '') {
                continue;
            }

            $department = Department::firstOrCreate(
                ['organization_id' => $row->organization_id, 'name' => $name],
                ['created_at' => $now, 'updated_at' => $now]
            );

            OrganizationMember::where('organization_id', $row->organization_id)
                ->where('department', $row->department)
                ->update(['department_id' => $department->id]);

            Invitation::where('organization_id', $row->organization_id)
                ->where('department', $row->department)
                ->update(['department_id' => $department->id]);
        }
    }

    public function down(): void
    {
        // Data-only backfill — the string columns it read from are dropped by
        // the very next migration, so there is nothing meaningful to reverse
        // into; department_id values are left as-is.
    }
};
