<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TourismTariffSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('tourism_tariffs')->exists()) {
            return;
        }

        DB::table('tourism_tariffs')->insert([
            'concept' => 'Tarjeta de Turismo',
            'amount' => 153000,
            'currency' => 'COP',
            'active' => 1,
            'starts_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
