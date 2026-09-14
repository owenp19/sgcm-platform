<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PopulationZoneSeeder extends Seeder
{
    public function run(): void
    {
        $zones = [
            [
                'name' => 'North End (Norte)',
                'zone_type' => 'sector',
                'latitude' => 12.5847000,
                'longitude' => -81.7006000,
                'total_population' => 4800,
                'raizal_population' => 950,
                'other_population' => 3850,
                'description' => 'Zona turística y comercial principal de San Andrés. Alta concentración de visitantes y comercio.',
            ],
            [
                'name' => 'Sarie Bay',
                'zone_type' => 'sector',
                'latitude' => 12.5881000,
                'longitude' => -81.7042000,
                'total_population' => 2100,
                'raizal_population' => 620,
                'other_population' => 1480,
                'description' => 'Barrio de playa Sarie Bay con hoteles y viviendas.',
            ],
            [
                'name' => 'La Loma',
                'zone_type' => 'sector',
                'latitude' => 12.5333000,
                'longitude' => -81.7155000,
                'total_population' => 7200,
                'raizal_population' => 5100,
                'other_population' => 2100,
                'description' => 'Cuna de la cultura raizal, comunidad histórica en la colina central de la isla.',
            ],
            [
                'name' => 'San Luis',
                'zone_type' => 'sector',
                'latitude' => 12.5052000,
                'longitude' => -81.7062000,
                'total_population' => 4400,
                'raizal_population' => 2400,
                'other_population' => 2000,
                'description' => 'Sector costero oriental con restaurantes, posadas nativas y comunidad raizal.',
            ],
            [
                'name' => 'La Hormiga',
                'zone_type' => 'sector',
                'latitude' => 12.5562000,
                'longitude' => -81.7104000,
                'total_population' => 2900,
                'raizal_population' => 1150,
                'other_population' => 1750,
                'description' => 'Barrio residencial de la zona central de la isla.',
            ],
            [
                'name' => 'Los Almendros',
                'zone_type' => 'sector',
                'latitude' => 12.5440000,
                'longitude' => -81.7180000,
                'total_population' => 6800,
                'raizal_population' => 2200,
                'other_population' => 4600,
                'description' => 'Conjunto residencial densamente poblado, de los más habitados de la isla.',
            ],
            [
                'name' => 'San Francisco',
                'zone_type' => 'sector',
                'latitude' => 12.5568000,
                'longitude' => -81.7268000,
                'total_population' => 2600,
                'raizal_population' => 980,
                'other_population' => 1620,
                'description' => 'Sector residencial y comercial cercano a la zona industrial.',
            ],
            [
                'name' => 'Camp (Champ)',
                'zone_type' => 'sector',
                'latitude' => 12.5585000,
                'longitude' => -81.7330000,
                'total_population' => 4100,
                'raizal_population' => 1900,
                'other_population' => 2200,
                'description' => 'Comunidad tradicional raizal en la costa occidental.',
            ],
            [
                'name' => 'Simpson Well',
                'zone_type' => 'sector',
                'latitude' => 12.5550000,
                'longitude' => -81.7186000,
                'total_population' => 2300,
                'raizal_population' => 1200,
                'other_population' => 1100,
                'description' => 'Barrio raizal tradicional en el centro de la isla.',
            ],
            [
                'name' => 'El Cove',
                'zone_type' => 'sector',
                'latitude' => 12.6262000,
                'longitude' => -81.7050000,
                'total_population' => 3500,
                'raizal_population' => 1600,
                'other_population' => 1900,
                'description' => 'Población en el extremo norte de la isla, zona de pescadores.',
            ],
            [
                'name' => 'Cueva de Morgan',
                'zone_type' => 'sector',
                'latitude' => 12.6032000,
                'longitude' => -81.6890000,
                'total_population' => 850,
                'raizal_population' => 420,
                'other_population' => 430,
                'description' => 'Zona de atractivo turístico y viviendas dispersas.',
            ],
            [
                'name' => 'Natania',
                'zone_type' => 'sector',
                'latitude' => 12.5200000,
                'longitude' => -81.7200000,
                'total_population' => 1500,
                'raizal_population' => 700,
                'other_population' => 800,
                'description' => 'Asentamiento raizal en la zona sur de la isla.',
            ],
            [
                'name' => 'Barrack',
                'zone_type' => 'sector',
                'latitude' => 12.5290000,
                'longitude' => -81.7120000,
                'total_population' => 1150,
                'raizal_population' => 850,
                'other_population' => 300,
                'description' => 'Comunidad histórica raizal, una de las más antiguas de la isla.',
            ],
            [
                'name' => 'Tom Hooker (Busch)',
                'zone_type' => 'sector',
                'latitude' => 12.5160000,
                'longitude' => -81.7090000,
                'total_population' => 1300,
                'raizal_population' => 910,
                'other_population' => 390,
                'description' => 'Sector raizal al sur, cerca de San Luis.',
            ],
        ];

        foreach ($zones as $zone) {
            $raizal = $zone['raizal_population'];
            $total = $zone['total_population'];
            $other = $total - $raizal;
            $pctRaizal = ($total > 0) ? ($raizal / $total) : 0;

            if ($pctRaizal >= 0.60) {
                $color = '#1d72b5';
            } elseif ($pctRaizal >= 0.40) {
                $color = '#16a34a';
            } elseif ($pctRaizal >= 0.25) {
                $color = '#d97706';
            } else {
                $color = '#dc2626';
            }

            DB::table('population_zones')->updateOrInsert(
                ['name' => $zone['name']],
                $zone + [
                    'other_population' => $other,
                    'color' => $color,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}