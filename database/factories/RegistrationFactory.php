<?php

namespace Database\Factories;

use App\Models\Church;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Registration>
 */
class RegistrationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'church_id' => Church::factory(),
            'user_id' => User::factory(),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => (string) fake()->numberBetween(84900000000, 84999999999),
            'payment_status' => 'pending',
            'payment_id' => null,
            'pix_qr_code' => null,
            'receipt_path' => 'receipts/exemplo.jpg',
            'amount_paid' => fake()->randomFloat(2, 0, 250),
            'custom_answers' => [],
        ];
    }

    public function paid(): static
    {
        return $this->state(['payment_status' => 'paid']);
    }
}
