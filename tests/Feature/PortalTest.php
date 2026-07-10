<?php

namespace Tests\Feature;

use App\Models\Empleado;
use App\Models\SolicitudVacaciones;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function trabajador(): array
    {
        $this->seed(CatalogoSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Empleado');
        $empleado = Empleado::create([
            'numero_documento' => '10101010', 'nombres' => 'Juan', 'apellidos' => 'Pérez', 'user_id' => $user->id,
        ]);
        $this->actingAs($user);

        return [$user, $empleado];
    }

    public function test_el_trabajador_solicita_sus_vacaciones(): void
    {
        [$user, $empleado] = $this->trabajador();

        Volt::test('portal.index')
            ->set('fecha_inicio', '2026-08-01')
            ->set('fecha_fin', '2026-08-05')
            ->set('motivo', 'Viaje')
            ->call('solicitar')
            ->assertHasNoErrors();

        $s = SolicitudVacaciones::where('empleado_id', $empleado->id)->first();
        $this->assertNotNull($s);
        $this->assertSame(5, $s->dias);
        $this->assertSame('pendiente', $s->estado);
    }

    public function test_el_trabajador_cancela_su_solicitud_pendiente(): void
    {
        [$user, $empleado] = $this->trabajador();
        $s = SolicitudVacaciones::create([
            'empleado_id' => $empleado->id, 'fecha_inicio' => '2026-08-01', 'fecha_fin' => '2026-08-03',
            'dias' => 3, 'estado' => 'pendiente',
        ]);

        Volt::test('portal.index')->call('cancelar', $s->id)->assertHasNoErrors();

        $this->assertSame('cancelada', $s->fresh()->estado);
    }

    public function test_usuario_sin_empleado_ve_aviso(): void
    {
        $this->seed(CatalogoSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Empleado');
        $this->actingAs($user);

        Volt::test('portal.index')->assertSee('no está vinculado');
    }
}
