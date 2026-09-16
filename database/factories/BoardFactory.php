<?php

namespace Database\Factories;

use App\Models\Board;
use App\Models\Church;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Board>
 */
class BoardFactory extends Factory
{
    /**
     * A versão do Blueprint listava `year_start`, `year_end` e `secretary_name`,
     * que não existem na tabela — a real tem primeiro/segundo/executivo.
     */
    public function definition(): array
    {
        return [
            'church_id' => Church::factory(),
            'president_name' => fake()->name(),
            'vice_president_name' => fake()->name(),
            'first_secretary_name' => fake()->name(),
            'second_secretary_name' => fake()->name(),
            'executive_secretary_name' => fake()->name(),
            'treasurer_name' => fake()->name(),
            'image_path' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
