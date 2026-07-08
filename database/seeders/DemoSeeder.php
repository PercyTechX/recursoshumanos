<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Cargo;
use App\Models\Empleado;
use App\Models\Sede;
use Illuminate\Database\Seeder;

/**
 * Datos de PRUEBA (desechables).
 *
 * Se ejecuta aparte:  php artisan db:seed --class=DemoSeeder
 * Para vaciar antes de producción existirá la acción "Vaciar datos de prueba".
 * Es idempotente (updateOrCreate / firstOrCreate).
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Catálogos
        $lima = Sede::firstOrCreate(['nombre' => 'Sede Lima'], ['direccion' => 'Av. Central 123']);
        $arequipa = Sede::firstOrCreate(['nombre' => 'Sede Arequipa'], ['direccion' => 'Calle Mercaderes 456']);

        $operaciones = Area::firstOrCreate(['nombre' => 'Operaciones']);
        $administracion = Area::firstOrCreate(['nombre' => 'Administración']);
        $gerenciaArea = Area::firstOrCreate(['nombre' => 'Gerencia']);
        Area::firstOrCreate(['nombre' => 'Mantenimiento', 'parent_id' => $operaciones->id]);

        $gerente = Cargo::firstOrCreate(['nombre' => 'Gerente']);
        $supervisor = Cargo::firstOrCreate(['nombre' => 'Supervisor']);
        $tecnico = Cargo::firstOrCreate(['nombre' => 'Técnico']);
        $asistente = Cargo::firstOrCreate(['nombre' => 'Asistente']);
        $analista = Cargo::firstOrCreate(['nombre' => 'Analista']);

        // Empleados de ejemplo
        $empleados = [
            ['12345678', 'Juan Carlos', 'Pérez Quispe',   $operaciones, $tecnico,    $lima,     'activo'],
            ['23456789', 'María Elena', 'Flores Ríos',     $administracion, $asistente, $lima,   'activo'],
            ['34567890', 'Carlos Alberto', 'Díaz Soto',    $operaciones, $supervisor, $lima,     'activo'],
            ['45678901', 'Ana Lucía', 'Torres Vega',       $administracion, $analista, $arequipa, 'activo'],
            ['56789012', 'Luis Miguel', 'Ramírez Cano',    $gerenciaArea, $gerente,    $lima,     'activo'],
            ['67890123', 'Rosa María', 'Huamán Ttito',     $operaciones, $tecnico,     $arequipa, 'activo'],
            ['78901234', 'Pedro Pablo', 'Santos León',     $operaciones, $tecnico,     $lima,     'cesado'],
            ['89012345', 'Carmen Rosa', 'Vargas Núñez',    $administracion, $asistente, $arequipa, 'activo'],
        ];

        foreach ($empleados as [$doc, $nombres, $apellidos, $area, $cargo, $sede, $situacion]) {
            Empleado::updateOrCreate(
                ['numero_documento' => $doc],
                [
                    'nombres' => $nombres,
                    'apellidos' => $apellidos,
                    'area_id' => $area->id,
                    'cargo_id' => $cargo->id,
                    'sede_id' => $sede->id,
                    'fecha_ingreso' => now()->subMonths(rand(3, 48))->toDateString(),
                    'situacion' => $situacion,
                    'correo' => strtolower(explode(' ', $nombres)[0]).'@empresa.test',
                    'telefono' => '9'.rand(10000000, 99999999),
                ],
            );
        }
    }
}
