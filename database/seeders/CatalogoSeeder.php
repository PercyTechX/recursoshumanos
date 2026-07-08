<?php

namespace Database\Seeders;

use App\Models\TipoDocumento;
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

        // Tipos de documento (con días de aviso previo al vencimiento)
        $tipos = [
            ['nombre' => 'SCTR Salud', 'dias_aviso_previo' => 30, 'requiere_vigencia' => true],
            ['nombre' => 'SCTR Pensión', 'dias_aviso_previo' => 30, 'requiere_vigencia' => true],
            ['nombre' => 'Antecedentes Penales', 'dias_aviso_previo' => 30, 'requiere_vigencia' => true],
            ['nombre' => 'Antecedentes Policiales', 'dias_aviso_previo' => 30, 'requiere_vigencia' => true],
            ['nombre' => 'Examen Médico Ocupacional (EMO)', 'dias_aviso_previo' => 60, 'requiere_vigencia' => true],
            ['nombre' => 'Certificado de Homologación', 'dias_aviso_previo' => 45, 'requiere_vigencia' => true],
            ['nombre' => 'Contrato de Trabajo', 'dias_aviso_previo' => 30, 'requiere_vigencia' => true],
            ['nombre' => 'Hoja de Vida (CV)', 'dias_aviso_previo' => 0, 'requiere_vigencia' => false],
        ];

        foreach ($tipos as $t) {
            TipoDocumento::firstOrCreate(['nombre' => $t['nombre']], $t);
        }
    }
}
