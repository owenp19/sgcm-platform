<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'admin', 'description' => 'Administrador del sistema con acceso completo'],
            ['name' => 'funcionario', 'description' => 'Funcionario de SGCM que gestiona trámites'],
            ['name' => 'ciudadano', 'description' => 'Usuario ciudadano que realiza trámites'],
        ];

        foreach ($roles as $role) {
            $exists = DB::table('roles')->where('name', $role['name'])->exists();

            if (! $exists) {
                DB::table('roles')->insert($role + ['created_at' => now()]);
            }
        }
    }
}
