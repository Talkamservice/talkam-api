<?php

namespace Database\Factories;

use App\Constants\Business\OrganizationConstants;
use App\Models\EmployeeSelfCheck;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeSelfCheckFactory extends Factory
{
    protected $model = EmployeeSelfCheck::class;

    public function definition(): array
    {
        return [
            "organization_id" => Organization::factory(),
            "user_id" => User::factory(),
            "category" => OrganizationConstants::SELF_CHECK_WORK,
            "score" => $this->faker->numberBetween(0, OrganizationConstants::SELF_CHECK_MAX_SCORE),
            "answered_at" => now(),
        ];
    }

    public function category(string $category): static
    {
        return $this->state(fn () => ["category" => $category]);
    }

    public function score(int $score): static
    {
        return $this->state(fn () => ["score" => $score]);
    }
}
