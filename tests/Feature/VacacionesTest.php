<?php

namespace Tests\Feature;

use App\Models\Empleado;
use App\Models\MovimientoVacaciones;
use App\Models\SolicitudVacaciones;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class VacacionesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function actorConRol(string $rol): User
    {
        $this->seed(CatalogoSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($rol);
        $this->actingAs($user);

        return $user;
    }

    public function test_crear_solicitud_calcula_dias_calendario(): void
    {
        $this->actorConRol('RRHH');
        $empleado = Empleado::create(['numero_documento' => '10101010', 'nombres' => 'Ana', 'apellidos' => 'Díaz']);

        Volt::test('vacaciones.tabla')
            ->call('nuevo')
            ->set('empleado_id', $empleado->id)
            ->set('fecha_inicio', '2026-08-01')
            ->set('fecha_fin', '2026-08-15')
            ->call('guardar')
            ->assertHasNoErrors();

        $s = SolicitudVacaciones::first();
        $this->assertSame(15, $s->dias); // inclusivo
        $this->assertSame('pendiente', $s->estado);
    }

    public function test_aprobar_descuenta_del_saldo(): void
    {
        $this->actorConRol('RRHH');
        $empleado = Empleado::create(['numero_documento' => '20202020', 'nombres' => 'Beto', 'apellidos' => 'Ruiz']);
        // Apertura de saldo: 30 días
        MovimientoVacaciones::create(['empleado_id' => $empleado->id, 'fecha' => '2026-01-01', 'tipo' => 'apertura', 'dias' => 30]);

        $s = SolicitudVacaciones::create([
            'empleado_id' => $empleado->id, 'fecha_inicio' => '2026-08-01', 'fecha_fin' => '2026-08-10',
            'dias' => 10, 'estado' => 'pendiente',
        ]);

        Volt::test('vacaciones.tabla')->call('aprobar', $s->id)->assertHasNoErrors();

        $this->assertSame('aprobada', $s->fresh()->estado);
        $this->assertDatabaseHas('movimientos_vacaciones', [
            'solicitud_id' => $s->id, 'tipo' => 'gozado', 'dias' => -10.00,
        ]);
        $this->assertSame(20.0, $empleado->fresh()->saldo_vacaciones); // 30 - 10
    }

    public function test_rechazar_no_genera_movimiento(): void
    {
        $this->actorConRol('Supervisor');
        $empleado = Empleado::create(['numero_documento' => '30303030', 'nombres' => 'Sol', 'apellidos' => 'Vega']);
        $s = SolicitudVacaciones::create([
            'empleado_id' => $empleado->id, 'fecha_inicio' => '2026-08-01', 'fecha_fin' => '2026-08-05',
            'dias' => 5, 'estado' => 'pendiente',
        ]);

        Volt::test('vacaciones.tabla')
            ->call('abrirRechazo', $s->id)
            ->set('comentario_decision', 'Fechas no disponibles')
            ->call('rechazar')
            ->assertHasNoErrors();

        $this->assertSame('rechazada', $s->fresh()->estado);
        $this->assertSame(0, MovimientoVacaciones::where('empleado_id', $empleado->id)->count());
    }

    public function test_registrar_apertura_desde_el_expediente(): void
    {
        $this->actorConRol('RRHH');
        $empleado = Empleado::create(['numero_documento' => '40404040', 'nombres' => 'Iván', 'apellidos' => 'Soto']);

        Volt::test('empleados.expediente', ['empleado' => $empleado])
            ->call('abrirMov')
            ->set('mov_tipo', 'apertura')
            ->set('mov_dias', '22.5')
            ->set('mov_fecha', '2026-07-01')
            ->call('guardarMov')
            ->assertHasNoErrors();

        $this->assertSame(22.5, $empleado->fresh()->saldo_vacaciones);
    }
}
