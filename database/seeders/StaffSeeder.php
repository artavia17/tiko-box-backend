<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Cuenta de administrador inicial para entrar a la app interna.
 * Las credenciales salen del .env: no se guardan en el repositorio.
 */
class StaffSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! $email || ! $password) {
            $this->command?->warn('Definí ADMIN_EMAIL y ADMIN_PASSWORD en el .env para crear el administrador.');

            return;
        }

        $admin = User::updateOrCreate(
            ['email' => $email],
            [
                'role' => 'admin',
                'first_name' => env('ADMIN_FIRST_NAME', 'Administrador'),
                'last_name' => env('ADMIN_LAST_NAME', 'Tikabox'),
                'name' => trim(env('ADMIN_FIRST_NAME', 'Administrador').' '.env('ADMIN_LAST_NAME', 'Tikabox')),
                'password' => $password,
                'email_verified_at' => now(),
            ],
        );

        $this->command?->info("Administrador listo: {$admin->email}");
    }
}
