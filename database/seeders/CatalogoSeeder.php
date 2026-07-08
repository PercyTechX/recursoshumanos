<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Datos PERMANENTES del sistema (no desechables).
 *
 * A diferencia de DemoSeeder (datos de prueba), este seeder crea catálogos y
 * roles que deben existir siempre, incluso en producción. Es idempotente:
 * se puede re-ejecutar sin duplicar.
 */
class CatalogoSeeder extends Seeder
{
    public function run(): void
    {
        // Roles del sistema
        foreach (['RRHH', 'Supervisor', 'Gerencia', 'Empleado'] as $rol) {
            Role::firstOrCreate(['name' => $rol]);
        }

        // Aquí irán, más adelante, catálogos permanentes:
        // tipos_documento, tipos_solicitud, etc.
    }
}
