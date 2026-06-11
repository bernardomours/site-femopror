<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Church;

class ChurchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $igrejas = [
            'Igreja Presbiteriana do Planalto',
            'Igreja Presbiteriana do Abolição',
            'Igreja Presbiteriana Central de Mossoró',
            'Igreja Presbiteriana do Carnaubal',
            'Igreja Presbiteriana de Carnaubais',
            'Igreja Presbiteriana de Assú',
            'Igreja Presbiteriana das Barrocas',
        ];

        foreach ($igrejas as $igreja) {
            Church::updateOrCreate(
                ['name' => $igreja],
                ['is_federation' => true]
            );
        }
    }
}