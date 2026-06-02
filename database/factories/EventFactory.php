<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'slug' => fake()->slug(),
            'description' => fake()->text(),
            'event_date' => fake()->dateTime(),
            'location' => fake()->word(),
            'price' => fake()->randomFloat(2, 0, 99999999.99),
            'requires_receipt' => fake()->boolean(),
            'custom_fields' => '{}',
            'status' => fake()->randomElement(["draft","published","closed"]),
            'registrations' => fake()->word(),
        ];
    }
}
