<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OfficeSeeder extends Seeder
{
    public function run(): void
    {
        $fullDay = ['08:00', '08:30', '09:00', '09:30', '10:00', '10:30', '14:00', '14:30', '15:00', '15:30', '16:00', '16:30'];
        $halfDay = ['08:00', '09:00', '10:00', '14:00', '15:00', '16:00'];

        $offices = [
            [
                'name' => 'Oficina SGCM - San Andrés',
                'address' => 'Av. Francisco Newball, Edificio SGCM, San Andrés Islas',
                'phone' => '+57 318 123 4567',
                'hours_config' => json_encode([
                    'monday' => $fullDay,
                    'tuesday' => $fullDay,
                    'wednesday' => $fullDay,
                    'thursday' => $fullDay,
                    'friday' => $fullDay,
                    'saturday' => ['08:00', '09:00', '10:00'],
                    'sunday' => [],
                ]),
            ],
            [
                'name' => 'Punto de atención - Providencia',
                'address' => 'Calle Principal, Providencia Islas',
                'phone' => '+57 318 987 6543',
                'hours_config' => json_encode([
                    'monday' => $halfDay,
                    'tuesday' => $halfDay,
                    'wednesday' => $halfDay,
                    'thursday' => $halfDay,
                    'friday' => $halfDay,
                    'saturday' => [],
                    'sunday' => [],
                ]),
            ],
        ];

        foreach ($offices as $office) {
            DB::table('offices')->updateOrInsert(
                ['name' => $office['name']],
                $office + ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
