<?php

namespace Tests\Feature;

use App\Models\AsistenciaDia;
use App\Models\Empleado;
use App\Models\Marcacion;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AsistenciaResumenTest extends TestCase
{
    use RefreshDatabase;

    private Empleado $emp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(CatalogoSeeder::class);

        $this->emp = Empleado::create(['numero_documento' => '10101010', 'nombres' => 'Juan', 'apellidos' => 'Pérez']);
        // Jornada 08:00 → 17:00 (9h brutas)
        Marcacion::create(['empleado_id' => $this->emp->id, 'tipo' => 'ingreso', 'fecha_hora' => '2026-07-10 08:00:00']);
        Marcacion::create(['empleado_id' => $this->emp->id, 'tipo' => 'salida', 'fecha_hora' => '2026-07-10 17:00:00']);
    }

    private function supervisor(): User
    {
        $u = User::factory()->create();
        $u->assignRole('Supervisor');

        return $u;
    }

    private function rrhh(): User
    {
        $u = User::factory()->create();
        $u->assignRole('RRHH');

        return $u;
    }

    public function test_marcar_refrigerio_crea_el_dia_y_descuenta(): void
    {
        $this->actingAs($this->supervisor());

        Volt::test('asistencia.resumen')
            ->set('desde', '2026-07-01')->set('hasta', '2026-07-31')
            ->call('toggleRefrigerio', $this->emp->id, '2026-07-10', 'almuerzo')
            ->assertHasNoErrors()
            ->assertSee('8h 0m'); // 9h brutas − 1h almuerzo = 8h netas

        $d = AsistenciaDia::first();
        $this->assertTrue($d->almuerzo);
        $this->assertSame(60, $d->refrigerio_minutos);
    }

    public function test_solo_supervisor_puede_dar_vb(): void
    {
        // RRHH no tiene asistencia.vb
        $this->actingAs($this->rrhh());
        Volt::test('asistencia.resumen')
            ->call('toggleVb', $this->emp->id, '2026-07-10')
            ->assertForbidden();
        $this->assertSame(0, AsistenciaDia::where('vb_supervisor', true)->count());

        // Supervisor sí
        $sup = $this->supervisor();
        $this->actingAs($sup);
        Volt::test('asistencia.resumen')
            ->call('toggleVb', $this->emp->id, '2026-07-10')
            ->assertHasNoErrors();

        $d = AsistenciaDia::first();
        $this->assertTrue($d->vb_supervisor);
        $this->assertSame($sup->id, $d->vb_por);
        $this->assertNotNull($d->vb_at);
    }

    public function test_toggle_vb_dos_veces_lo_quita(): void
    {
        $sup = $this->supervisor();
        $this->actingAs($sup);

        Volt::test('asistencia.resumen')
            ->call('toggleVb', $this->emp->id, '2026-07-10')
            ->call('toggleVb', $this->emp->id, '2026-07-10')
            ->assertHasNoErrors();

        $d = AsistenciaDia::first();
        $this->assertFalse($d->vb_supervisor);
        $this->assertNull($d->vb_por);
    }
}
