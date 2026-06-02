<?php

namespace Database\Factories;

use App\Models\Church;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

class RegistrationFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'church_id' => Church::factory(),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'payment_status' => fake()->randomElement(["pending","paid","failed"]),
            'payment_id' => fake()->word(),
            'pix_qr_code' => fake()->text(),
            'receipt_path' => fake()->word(),
            'custom_answers' => '{}',
        ];
    }
}
