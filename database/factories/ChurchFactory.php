<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ChurchFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'is_federation' => fake()->boolean(),
            'boards' => fake()->word(),
            'registrations' => fake()->word(),
        ];
    }
}
