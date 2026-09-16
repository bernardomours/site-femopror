<?php

namespace Database\Factories;

use App\Models\Church;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Church>
 */
class ChurchFactory extends Factory
{
    /**
     * `boards` e `registrations` estavam aqui como se fossem colunas — são
     * relações. Além de quebrar o insert, o nome vinha de fake()->name(),
     * que gera nome de pessoa.
     */
    public function definition(): array
    {
        return [
            'name' => 'Igreja Presbiteriana '.fake()->unique()->city(),
            'is_federation' => true,
        ];
    }

    public function outsideFederation(): static
    {
        return $this->state(['is_federation' => false]);
    }
}
