<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AppointmentServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            [
                'name' => 'Trámites de tarjeta SGCM',
                'slug' => 'tarjeta-occre',
                'description' => 'Mi primera tarjeta, renovación, duplicado, correcciones, etc.',
                'icon' => 'document-text-outline',
                'requires_documents' => 1,
                'duration_minutes' => 15,
            ],
            [
                'name' => 'Permiso temporal de trabajador foráneo',
                'slug' => 'trabajador-foraneo',
                'description' => 'Vinculación laboral o permanencia temporal.',
                'icon' => 'people-outline',
                'requires_documents' => 1,
                'duration_minutes' => 20,
            ],
            [
                'name' => 'Consulta de estado de trámite',
                'slug' => 'consulta-estado',
                'description' => 'Revisa el estado de tu solicitud o radicado.',
                'icon' => 'document-text-outline',
                'requires_documents' => 0,
                'duration_minutes' => 10,
            ],
            [
                'name' => 'Orientación general',
                'slug' => 'orientacion-general',
                'description' => 'Información sobre trámites y requisitos.',
                'icon' => 'chatbubble-ellipses',
                'requires_documents' => 0,
                'duration_minutes' => 15,
            ],
        ];

        foreach ($services as $service) {
            DB::table('appointment_services')->updateOrInsert(
                ['slug' => $service['slug']],
                $service + ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
