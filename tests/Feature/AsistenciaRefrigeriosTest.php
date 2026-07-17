<?php

namespace Tests\Feature;

use App\Models\AsistenciaDia;
use App\Models\Empleado;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AsistenciaRefrigeriosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_refrigerio_descuenta_una_hora_por_comida(): void
    {
        $emp = Empleado::create(['numero_documento' => '10101010', 'nombres' => 'Ana', 'apellidos' => 'Díaz']);
        $dia = AsistenciaDia::create(['empleado_id' => $emp->id, 'fecha' => '2026-07-10', 'almuerzo' => true, 'cena' => true]);

        $this->assertSame(120, $dia->refrigerio_minutos); // 2 comidas × 60
    }

    public function test_horas_netas_no_bajan_de_cero(): void
    {
        // 9h brutas − 1 refrigerio = 8h (480 min)
        $this->assertSame(480, AsistenciaDia::minutosNetos(540, 60));
        // Nunca negativo
        $this->assertSame(0, AsistenciaDia::minutosNetos(30, 60));
    }

    public function test_solo_supervisor_recibe_el_permiso_vb_por_defecto(): void
    {
        $this->seed(CatalogoSeeder::class);

        $this->assertTrue(Permission::where('name', 'asistencia.vb')->exists());
        $this->assertTrue(Role::findByName('Supervisor')->hasPermissionTo('asistencia.vb'));
        $this->assertFalse(Role::findByName('RRHH')->hasPermissionTo('asistencia.vb'));
        $this->assertFalse(Role::findByName('Gerencia')->hasPermissionTo('asistencia.vb'));
    }
}
