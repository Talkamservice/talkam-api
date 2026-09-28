<?php

use App\Models\Organization;
use App\Models\Therapist;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-time backfill for `organization_therapists`.
 *
 * Before this batch, "in network" was derived live from bench-topic specialty
 * overlap (see OrgRosterService), with an explicit membership table
 * introduced by this batch as the new source of truth. This snapshots
 * whichever verified therapists actually overlapped an org's bench topics at
 * migration time, so admins don't see their apparent network go empty at
 * deploy.
 *
 * Deliberately NOT backfilled: the old logic's "empty bench_topics -> every
 * verified therapist counts as in-network" branch. That was blanket-default
 * behaviour, not a real network signal, and backfilling it would seed every
 * org with the entire verified-therapist directory.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $organizations = Organization::query()
            ->whereNotNull('bench_topics')
            ->get(['id', 'bench_topics']);

        $therapists = Therapist::query()
            ->whereNotNull('verified_at')
            ->with('user.therapistApplications.specialties')
            ->get();

        $now = now();
        $rows = [];

        foreach ($organizations as $organization) {
            $bench_ids = collect($organization->bench_topics ?? [])
                ->map(fn ($k) => (int) $k)
                ->filter()
                ->values();

            if ($bench_ids->isEmpty()) {
                continue;
            }

            foreach ($therapists as $therapist) {
                $application = $therapist->user?->therapistApplications
                    ?->where('status', 'approved')
                    ->sortByDesc('created_at')
                    ->first();

                $specialty_ids = collect($application?->specialties ?? [])
                    ->pluck('category_id')
                    ->filter()
                    ->map(fn ($id) => (int) $id);

                if ($specialty_ids->intersect($bench_ids)->isEmpty()) {
                    continue;
                }

                $rows[] = [
                    'organization_id' => $organization->id,
                    'therapist_id' => $therapist->id,
                    'status' => 'active',
                    'added_by' => null,
                    'added_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('organization_therapists')->insertOrIgnore($chunk);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data-only backfill — nothing structural to reverse. Rows added here
        // are indistinguishable from real admin actions taken since, so a
        // blanket delete would risk destroying genuine data.
    }
};
