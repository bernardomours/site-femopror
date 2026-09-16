<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * A versão gerada pelo Blueprint trazia `'registrations' => fake()->word()`
     * — coluna que não existe: qualquer create() morria no SQL. O preço também
     * sorteava até 99 milhões, o que não ajuda a ler nenhum teste.
     */
    public function definition(): array
    {
        $titulo = fake()->unique()->sentence(3);

        return [
            'title' => $titulo,
            'slug' => Str::slug($titulo).'-'.fake()->unique()->numberBetween(1, 99999),
            'description' => fake()->paragraph(),
            'event_date' => now()->addMonth(),
            'opening_date' => null,
            'location' => fake()->city(),
            'price' => fake()->randomFloat(2, 0, 250),
            'requires_receipt' => true,
            'custom_fields' => null,
            'status' => 'published',
            'is_congress' => false,
            'image' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => 'draft']);
    }

    public function closed(): static
    {
        return $this->state(['status' => 'closed']);
    }

    public function congress(): static
    {
        return $this->state(['is_congress' => true]);
    }

    public function free(): static
    {
        return $this->state(['price' => 0, 'requires_receipt' => false]);
    }

    /** Publicado, mas com a inscrição ainda por abrir. */
    public function openingLater(): static
    {
        return $this->state(['opening_date' => now()->addWeek()]);
    }
}
