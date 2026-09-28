<?php

namespace App\Services\Business;

use App\Constants\General\StatusConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\Department;
use App\Models\Organization;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Departments are org-scoped, real rows (not a free-text column anymore) —
 * `organization_id` is the FK that ties each one to the org that created it,
 * so one org's "Engineering" is a distinct row from another org's, even
 * though the name string collides.
 */
class DepartmentService
{
    /**
     * All of an org's departments, alphabetical — the dropdown's source, and
     * (with the counts) the management page's own list. `people_count` is
     * current seat-holders plus still-outstanding invites, so a department
     * that's only ever been used on a pending invite doesn't look unused.
     */
    public static function list(Organization $organization): Collection
    {
        return Department::where('organization_id', $organization->id)
            ->withCount([
                'members',
                'invitations as pending_invitations_count' => fn ($q) => $q->where('status', StatusConstants::PENDING),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->name,
                'people_count' => $d->members_count + $d->pending_invitations_count,
            ]);
    }

    /** Tenant-scoped fetch — an id from another org is a not-found here. */
    public static function scopedById(Organization $organization, $id): Department
    {
        $department = Department::where('organization_id', $organization->id)
            ->where('id', $id)
            ->first();

        if (empty($department)) {
            throw new ModelNotFoundException('Department not found');
        }

        return $department;
    }

    /** Rename, from the management page's own edit action. */
    public static function update(Organization $organization, $id, string $name): Department
    {
        $department = self::scopedById($organization, $id);
        $name = trim($name);

        $validator = Validator::make(['name' => $name], [
            'name' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $exists = Department::where('organization_id', $organization->id)
            ->where('id', '!=', $department->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists();

        if ($exists) {
            throw new InvalidRequestException("\"{$name}\" already exists.");
        }

        $department->update(['name' => $name]);

        return $department->refresh();
    }

    /**
     * Both FKs (organization_members.department_id, invitations.department_id)
     * are nullOnDelete, so removing a department in use doesn't fail or
     * cascade-delete anyone — it just clears their department back to
     * "— No department —", same as manually unassigning each of them.
     */
    public static function delete(Organization $organization, $id): void
    {
        self::scopedById($organization, $id)->delete();
    }

    /**
     * Explicit create, from the admin UI's "+ Create new department". A
     * duplicate name (case-insensitive, matching the table's collation) is
     * rejected with a clear error rather than silently reused — unlike
     * findOrCreateByName() below, which backs invite/CSV flows where a
     * repeat name is the expected, common case.
     */
    public static function create(Organization $organization, string $name): Department
    {
        $name = trim($name);

        $validator = Validator::make(['name' => $name], [
            'name' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $exists = Department::where('organization_id', $organization->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists();

        if ($exists) {
            throw new InvalidRequestException("\"{$name}\" already exists.");
        }

        return Department::create([
            'organization_id' => $organization->id,
            'name' => $name,
        ]);
    }

    /**
     * Resolve-or-create by name — the invite and CSV-import paths only ever
     * have a typed string to go on (a bulk upload can't pre-select from a
     * list of departments it doesn't know exist yet), so a new name there
     * quietly becomes a new department rather than failing the whole invite.
     */
    public static function findOrCreateByName(Organization $organization, ?string $name): ?Department
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        $department = Department::where('organization_id', $organization->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        return $department ?? Department::create([
            'organization_id' => $organization->id,
            'name' => $name,
        ]);
    }
}
