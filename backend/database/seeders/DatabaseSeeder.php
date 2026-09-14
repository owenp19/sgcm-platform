<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            ProcedureSeeder::class,
            AppointmentServiceSeeder::class,
            OfficeSeeder::class,
            TourismTariffSeeder::class,
            PopulationZoneSeeder::class,
        ]);
    }
}
