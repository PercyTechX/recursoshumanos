<?php

namespace Tests\Feature;

use App\Models\Empleado;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FichaEmpleadoTest extends TestCase
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

    public function test_rrhh_guarda_la_ficha_completa_con_sueldo(): void
    {
        $this->actorConRol('RRHH');

        Volt::test('empleados.tabla')
            ->call('nuevo')
            ->set('numero_documento', '11223344')
            ->set('nombres', 'Ana')
            ->set('apellidos', 'López')
            ->set('sueldo', '2500.50')
            ->set('cci', '00212300012345678901')
            ->set('emergencia_nombre', 'María López')
            ->set('emergencia_telefono', '999888777')
            ->call('guardar')
            ->assertHasNoErrors();

        $e = Empleado::where('numero_documento', '11223344')->first();
        $this->assertSame('2500.50', (string) $e->sueldo);
        $this->assertSame('00212300012345678901', $e->cci);
        $this->assertSame('María López', $e->emergencia_nombre);
    }

    public function test_el_supervisor_no_puede_modificar_el_sueldo(): void
    {
        // El empleado ya tiene un sueldo cargado
        $this->seed(CatalogoSeeder::class);
        $empleado = Empleado::create([
            'numero_documento' => '55667788', 'nombres' => 'Beto', 'apellidos' => 'Ruiz', 'sueldo' => 3000,
        ]);

        $user = User::factory()->create();
        $user->assignRole('Supervisor');
        $this->actingAs($user);

        Volt::test('empleados.tabla')
            ->call('editar', $empleado->id)
            ->set('sueldo', '9999')   // intento de modificar
            ->call('guardar')
            ->assertHasNoErrors();

        // El sueldo NO cambió
        $this->assertSame('3000.00', (string) $empleado->fresh()->sueldo);
    }
}
