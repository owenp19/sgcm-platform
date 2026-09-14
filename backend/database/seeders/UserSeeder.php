<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Usuario de prueba — login desde /login (SOLO app, rol ciudadano).
        // Por separacion de roles NO puede entrar al panel /admin (solo funcionario/admin).
        $usuario = User::firstOrCreate(
            ['email' => env('SEED_EMAIL', 'owen@sgcm.app')],
            [
                'first_name' => env('SEED_FIRST_NAME', 'Owen'),
                'last_name' => env('SEED_LAST_NAME', 'SGCM'),
                'password' => Hash::make(env('SEED_PASSWORD', '1234567')),
            ]
        );
        $usuario->roles()->sync([DB::table('roles')->where('name', 'ciudadano')->value('id')]);

        // Funcionario de prueba — login desde /admin/login (rol funcionario).
        // Tiene potestad de gestionar usuarios del panel (lista y cambiar roles).
        $funcionario = User::firstOrCreate(
            ['email' => env('SEED_FUNCIONARIO_EMAIL', 'funcionario@sgcm.app')],
            [
                'first_name' => env('SEED_FUNCIONARIO_FIRST_NAME', 'Owen'),
                'last_name' => env('SEED_FUNCIONARIO_LAST_NAME', 'Suárez'),
                'password' => Hash::make(env('SEED_FUNCIONARIO_PASSWORD', '1234567')),
            ]
        );
        $funcionario->roles()->sync([DB::table('roles')->where('name', 'funcionario')->value('id')]);

        // Notificaciones de ejemplo para el usuario de prueba (solo si no tiene)
        if (! DB::table('notifications')->where('user_id', $usuario->id)->exists()) {
            $samples = [
                ['title' => 'Trámite actualizado', 'description' => 'Tu solicitud de tarjeta de residencia ha sido aprobada.', 'type' => 'success'],
                ['title' => 'Documentos requeridos', 'description' => 'Tu trámite de duplicado requiere documentos adicionales.', 'type' => 'alert'],
                ['title' => 'Recordatorio de cita', 'description' => 'Tienes una cita programada para el próximo 30 de mayo.', 'type' => 'info'],
                ['title' => 'Pago confirmado', 'description' => 'El pago de tu tarjeta de turismo ha sido recibido.', 'type' => 'success'],
                ['title' => 'Cambio en horarios', 'description' => 'La SGCM informa que el horario de atención se modificará en junio.', 'type' => 'alert'],
            ];

            foreach ($samples as $sample) {
                DB::table('notifications')->insert($sample + [
                    'user_id' => $usuario->id,
                    'read' => 0,
                    'created_at' => now(),
                ]);
            }
        }
    }
}
