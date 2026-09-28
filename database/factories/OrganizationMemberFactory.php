<?php

namespace Database\Factories;

use App\Constants\Business\OrganizationConstants;
use App\Models\Department;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationMemberFactory extends Factory
{
    protected $model = OrganizationMember::class;

    public function definition(): array
    {
        return [
            "organization_id" => Organization::factory(),
            "user_id" => User::factory(),
            "role" => OrganizationConstants::ROLE_EMPLOYEE,
            "status" => OrganizationConstants::MEMBER_ACTIVE,
            // No default department — unlike the old free-text column, a
            // department here is a real row scoped to a specific org, so
            // there's no name ("Technology") that's safe to conjure without
            // knowing which org this member belongs to. Use inDepartment()
            // to attach a real one once the org is known.
            "department_id" => null,
            "activated_at" => now(),
        ];
    }

    /** Pin the member to an existing department (and, implicitly, its org) —
     *  used by tests that assert on a specific department name/grouping. */
    public function inDepartment(Department $department): static
    {
        return $this->state(fn () => [
            "organization_id" => $department->organization_id,
            "department_id" => $department->id,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn () => ["role" => OrganizationConstants::ROLE_ADMIN]);
    }

    public function employee(): static
    {
        return $this->state(fn () => ["role" => OrganizationConstants::ROLE_EMPLOYEE]);
    }

    public function therapist(): static
    {
        return $this->state(fn () => ["role" => OrganizationConstants::ROLE_THERAPIST]);
    }

    public function invited(): static
    {
        return $this->state(fn () => [
            "status" => OrganizationConstants::MEMBER_INVITED,
            "activated_at" => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            "status" => OrganizationConstants::MEMBER_INACTIVE,
            "deactivated_at" => now(),
        ]);
    }
}
