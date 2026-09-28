<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        return [
            "organization_id" => Organization::factory(),
            "name" => fake()->unique()->randomElement([
                "Engineering", "Sales", "Marketing", "Finance", "Operations",
                "Customer Success", "People", "Legal", "Product", "Design",
            ]),
        ];
    }
}
