<?php

namespace Database\Factories;

use App\Models\Church;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /** Administrador da federação: enxerga todas as igrejas. */
    public function admin(): static
    {
        return $this->state(['is_admin' => true, 'church_id' => null]);
    }

    /** Presidente de UMP local: acessa /ump, escopado à própria igreja. */
    public function ofChurch(Church|int $church): static
    {
        return $this->state([
            'is_admin' => false,
            'is_church_president' => true,
            'church_id' => $church instanceof Church ? $church->id : $church,
        ]);
    }

    /**
     * Jovem que só escolheu a própria igreja no perfil. Não é presidente e
     * portanto NÃO acessa o /ump — a distinção que faz o campo poder ficar
     * aberto no perfil.
     */
    public function memberOfChurch(Church|int $church): static
    {
        return $this->state([
            'is_admin' => false,
            'is_church_president' => false,
            'church_id' => $church instanceof Church ? $church->id : $church,
        ]);
    }
}
