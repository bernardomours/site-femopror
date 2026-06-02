<?php

namespace Database\Factories;

use App\Models\Church;
use Illuminate\Database\Eloquent\Factories\Factory;

class BoardFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'church_id' => Church::factory(),
            'year_start' => fake()->numberBetween(-10000, 10000),
            'year_end' => fake()->numberBetween(-10000, 10000),
            'president_name' => fake()->word(),
            'vice_president_name' => fake()->word(),
            'secretary_name' => fake()->word(),
            'treasurer_name' => fake()->word(),
            'image_path' => fake()->word(),
            'is_active' => fake()->boolean(),
        ];
    }
}
