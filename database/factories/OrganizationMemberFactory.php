<?php

namespace Database\Factories;

use App\Constants\Business\OrganizationConstants;
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
            "department" => "Technology",
            "activated_at" => now(),
        ];
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
