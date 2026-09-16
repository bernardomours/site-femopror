<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Ferramenta de desenvolvimento. Criava um admin com e-mail `a@a` e senha
     * `123` — e admin da federação enxerga todas as igrejas, todos os
     * comprovantes e a lista de delegados de todo mundo.
     *
     * Em produção o primeiro acesso é `php artisan femopror:criar-admin`, que
     * pede a senha interativamente e exige 12 caracteres.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException(
                'DatabaseSeeder é ferramenta de desenvolvimento. Em produção use: php artisan femopror:criar-admin'
            );
        }

        User::updateOrCreate(
            ['email' => 'admin@femopror.test'],
            [
                'name' => 'Admin (desenvolvimento)',
                'password' => 'senha-de-desenvolvimento',
                'email_verified_at' => now(),
            ],
        )->forceFill(['is_admin' => true])->save();

        $this->call([
            ChurchSeeder::class,
        ]);
    }
}
