<?php

namespace Database\Seeders;

use App\Models\CategoriaActivo;
use App\Models\TipoDocumento;
use App\Models\TipoEpp;
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
        // Roles del sistema (SuperAdmin ve y hace todo — vía Gate::before)
        foreach (['SuperAdmin', 'RRHH', 'Supervisor', 'Gerencia', 'Empleado', 'Contador'] as $rol) {
            Role::firstOrCreate(['name' => $rol]);
        }

        // Tipos de documento (con días de aviso previo al vencimiento).
        // compartible = un solo archivo ampara a varias personas (SCTR, homologación).
        $tipos = [
            ['nombre' => 'SCTR Salud', 'dias_aviso_previo' => 30, 'requiere_vigencia' => true, 'compartible' => true],
            ['nombre' => 'SCTR Pensión', 'dias_aviso_previo' => 30, 'requiere_vigencia' => true, 'compartible' => true],
            ['nombre' => 'Antecedentes Penales', 'dias_aviso_previo' => 30, 'requiere_vigencia' => true, 'compartible' => false],
            ['nombre' => 'Antecedentes Policiales', 'dias_aviso_previo' => 30, 'requiere_vigencia' => true, 'compartible' => false],
            ['nombre' => 'Examen Médico Ocupacional (EMO)', 'dias_aviso_previo' => 60, 'requiere_vigencia' => true, 'compartible' => false],
            ['nombre' => 'Certificado de Homologación', 'dias_aviso_previo' => 45, 'requiere_vigencia' => true, 'compartible' => true],
            ['nombre' => 'Contrato de Trabajo', 'dias_aviso_previo' => 30, 'requiere_vigencia' => true, 'compartible' => false],
            ['nombre' => 'Hoja de Vida (CV)', 'dias_aviso_previo' => 0, 'requiere_vigencia' => false, 'compartible' => false],
        ];

        foreach ($tipos as $t) {
            // updateOrCreate para que 'compartible' se aplique también a tipos ya existentes.
            TipoDocumento::updateOrCreate(['nombre' => $t['nombre']], $t);
        }

        // Categorías de activos (retornables)
        foreach (['Herramientas', 'Equipos electrónicos', 'Vehículos', 'Mobiliario'] as $cat) {
            CategoriaActivo::firstOrCreate(['nombre' => $cat]);
        }

        // Tipos de EPP (consumibles)
        foreach (['Polo', 'Botas', 'Chaleco', 'Guantes', 'Casco', 'Lentes de seguridad'] as $epp) {
            TipoEpp::firstOrCreate(['nombre' => $epp], ['controla_talla' => in_array($epp, ['Polo', 'Botas', 'Chaleco'])]);
        }
    }
}
