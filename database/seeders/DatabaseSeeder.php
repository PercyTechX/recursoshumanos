<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1) Datos permanentes (roles, catálogos). Siempre se ejecuta.
        $this->call(CatalogoSeeder::class);
        $this->call(UbigeoSeeder::class); // ubigeos del Perú (solo si está vacío)

        // 2) Usuario administrador inicial (Super Admin).
        $admin = User::firstOrCreate(
            ['email' => 'admin@rrhh.test'],
            ['name' => 'Administrador', 'password' => Hash::make('password')],
        );
        $admin->syncRoles(['SuperAdmin', 'RRHH']);

        // 3) Datos de prueba desechables (solo fuera de producción).
        // Se ejecuta aparte con: php artisan db:seed --class=DemoSeeder
    }
}
