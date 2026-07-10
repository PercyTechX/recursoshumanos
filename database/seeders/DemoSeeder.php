<?php

namespace Database\Seeders;

use App\Models\Activo;
use App\Models\Area;
use App\Models\Ausencia;
use App\Models\Cliente;
use App\Models\Sucursal;
use App\Models\Cargo;
use App\Models\CategoriaActivo;
use App\Models\Derechohabiente;
use App\Models\Documento;
use App\Models\DocumentoCompartido;
use App\Models\Empleado;
use App\Models\Sede;
use App\Models\TipoDocumento;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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
                    'modalidad_pago' => $situacion === 'cesado' ? 'planilla' : (rand(0, 3) ? 'planilla' : 'honorarios'),
                    'sistema_pensionario' => rand(0, 1) ? 'AFP' : 'ONP',
                    'afp_nombre' => rand(0, 1) ? 'Integra' : 'Prima',
                    'regimen_salud' => 'EsSalud',
                    'tiene_seguro' => (bool) rand(0, 1),
                ],
            );
        }

        // Organigrama de ejemplo (supervisores) — habilita el aviso al supervisor
        $ids = Empleado::pluck('id', 'numero_documento');
        $jefeOperaciones = $ids['34567890'] ?? null; // Carlos (Supervisor)
        $gerente = $ids['56789012'] ?? null;          // Luis (Gerente)
        if ($jefeOperaciones) {
            Empleado::whereIn('numero_documento', ['12345678', '67890123', '78901234', '89012345'])
                ->update(['supervisor_id' => $jefeOperaciones]);
        }
        if ($gerente) {
            Empleado::whereIn('numero_documento', ['34567890', '23456789', '45678901'])
                ->update(['supervisor_id' => $gerente]);
        }

        // Derechohabientes de ejemplo (familia de Juan Carlos Pérez)
        $juan = Empleado::where('numero_documento', '12345678')->first();
        if ($juan) {
            $familia = [
                ['conyuge', 'Lucía', 'Ramos Gil', 'DNI', '41222333', '1990-05-12', 'esposa'],
                ['hijo', 'Mateo', 'Pérez Ramos', 'DNI', '75111222', '2015-08-01', 'hijo'],
                ['hijo', 'Valentina', 'Pérez Ramos', 'PARTIDA', null, '2019-03-20', 'hija'],
            ];
            foreach ($familia as [$tipo, $nom, $ape, $td, $nd, $fnac, $paren]) {
                Derechohabiente::firstOrCreate(
                    ['empleado_id' => $juan->id, 'nombres' => $nom, 'apellidos' => $ape],
                    ['tipo' => $tipo, 'tipo_documento' => $td, 'numero_documento' => $nd, 'fecha_nacimiento' => $fnac, 'parentesco' => $paren],
                );
            }
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

        // SCTR colectivo de ejemplo (un archivo ampara a varias personas)
        if (DocumentoCompartido::count() === 0) {
            $sctrSalud = $tipo['SCTR Salud'] ?? null;
            $sctrPension = $tipo['SCTR Pensión'] ?? null;
            $ampara = Empleado::where('situacion', 'activo')->take(6)->pluck('id');
            if ($sctrSalud && $sctrPension && $ampara->isNotEmpty()) {
                $dc = DocumentoCompartido::create([
                    'fecha_emision' => now()->subDays(10)->toDateString(),
                    'fecha_vencimiento' => now()->addDays(20)->toDateString(), // 🟡 por vencer
                    'observacion' => 'Póliza colectiva de demostración',
                ]);
                $dc->coberturas()->createMany([
                    ['tipo_documento_id' => $sctrSalud, 'aseguradora' => 'Sanitas', 'numero_poliza' => '670949'],
                    ['tipo_documento_id' => $sctrPension, 'aseguradora' => 'Crecer', 'numero_poliza' => '9000182972'],
                ]);
                $dc->empleados()->sync($ampara->all());
            }
        }

        // Usuario-trabajador de ejemplo (para probar el portal del trabajador)
        $juanId = $ids['12345678'] ?? null;
        if ($juanId) {
            $tecnico = User::firstOrCreate(
                ['email' => 'tecnico@empresa.test'],
                ['name' => 'Juan Carlos Pérez', 'password' => Hash::make('password')],
            );
            $tecnico->syncRoles(['Empleado']);
            Empleado::whereKey($juanId)->update(['user_id' => $tecnico->id]);
        }

        // Ausencia de ejemplo (descanso médico)
        $juanId = $ids['12345678'] ?? null;
        if ($juanId && Ausencia::count() === 0) {
            Ausencia::create([
                'empleado_id' => $juanId, 'tipo' => 'descanso_medico', 'con_goce' => true,
                'fecha_inicio' => now()->subDays(8)->toDateString(), 'fecha_fin' => now()->subDays(4)->toDateString(),
                'dias' => 5, 'documento_ref' => 'CITT N° 000123', 'motivo' => 'Descanso médico de ejemplo',
            ]);
        }

        // Geocerca de ejemplo en la sede Lima
        Sede::where('nombre', 'Sede Lima')->update([
            'tipo' => 'oficina', 'latitud' => -12.0464000, 'longitud' => -77.0428000, 'radio_metros' => 150,
        ]);

        // Cliente + sucursal de ejemplo (con geocerca)
        $cliente = Cliente::firstOrCreate(
            ['ruc' => '20123456789'],
            ['razon_social' => 'Comercial Andina S.A.C.', 'nombre_comercial' => 'Andina'],
        );
        Sucursal::firstOrCreate(
            ['cliente_id' => $cliente->id, 'nombre' => 'Tienda Miraflores'],
            [
                'direccion' => 'Av. Larco 345', 'latitud' => -12.1211000, 'longitud' => -77.0299000,
                'radio_metros' => 120, 'departamento' => 'LIMA', 'provincia' => 'LIMA', 'distrito' => 'MIRAFLORES',
                'centro_costo' => 'CC-001',
            ],
        );

        // Activos de ejemplo (retornables, disponibles)
        $cat = CategoriaActivo::pluck('id', 'nombre');
        $activos = [
            ['Taladro Bosch GSB 550', 'HERR-001', 'Herramientas', 320.00],
            ['Taladro DeWalt DCD771', 'HERR-002', 'Herramientas', 450.00],
            ['Amoladora Makita', 'HERR-003', 'Herramientas', 280.00],
            ['Celular Samsung A15', 'EQ-001', 'Equipos electrónicos', 650.00],
            ['Laptop Lenovo V15', 'EQ-002', 'Equipos electrónicos', 2100.00],
        ];
        foreach ($activos as [$nombre, $codigo, $categoria, $costo]) {
            if (! isset($cat[$categoria])) {
                continue;
            }
            Activo::updateOrCreate(
                ['codigo' => $codigo],
                ['nombre' => $nombre, 'categoria_id' => $cat[$categoria], 'costo' => $costo, 'estado' => 'disponible'],
            );
        }
    }
}
