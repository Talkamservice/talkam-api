<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Country>
 */
class CountryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->country(),
            'shortname' => strtoupper(fake()->unique()->lexify('??')),
            'phonecode' => (string) fake()->numberBetween(1, 999),
        ];
    }
}
