<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Cargo;
use App\Models\Documento;
use App\Models\Empleado;
use App\Models\Sede;
use App\Models\TipoDocumento;
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
        // Garantiza catálogos permanentes (roles, tipos de documento).
        // Se usa call() (no callOnce): CatalogoSeeder es idempotente y así
        // funciona bien también entre tests (RefreshDatabase recrea la BD).
        $this->call(CatalogoSeeder::class);

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

        // Documentos de ejemplo (cubren los 3 estados del semáforo)
        $emp = Empleado::pluck('id', 'numero_documento');
        $tipo = TipoDocumento::pluck('id', 'nombre');

        // [documento_empleado, nombre_tipo, días hasta el vencimiento (desde hoy)]
        $docs = [
            ['12345678', 'SCTR Salud', 120],                      // 🟢 vigente
            ['12345678', 'Examen Médico Ocupacional (EMO)', 20],  // 🟡 por vencer (aviso 60)
            ['12345678', 'Antecedentes Penales', -10],            // 🔴 vencido
            ['34567890', 'Certificado de Homologación', 15],      // 🟡 por vencer (aviso 45)
            ['34567890', 'SCTR Pensión', 200],                    // 🟢 vigente
            ['23456789', 'Contrato de Trabajo', -5],              // 🔴 vencido
            ['45678901', 'SCTR Salud', 25],                       // 🟡 por vencer (aviso 30)
            ['56789012', 'Antecedentes Policiales', 300],         // 🟢 vigente
            ['67890123', 'Examen Médico Ocupacional (EMO)', -20], // 🔴 vencido
            ['89012345', 'SCTR Salud', 90],                       // 🟢 vigente
        ];

        foreach ($docs as [$dni, $tipoNombre, $offset]) {
            if (! isset($emp[$dni], $tipo[$tipoNombre])) {
                continue;
            }
            $venc = now()->addDays($offset)->toDateString();
            Documento::updateOrCreate(
                [
                    'empleado_id' => $emp[$dni],
                    'tipo_documento_id' => $tipo[$tipoNombre],
                    'fecha_vencimiento' => $venc,
                ],
                [
                    'fecha_emision' => now()->addDays($offset)->subYear()->toDateString(),
                    'observacion' => 'Documento de prueba',
                ],
            );
        }
    }
}
