<?php

namespace Tests\Feature;

use App\Models\Empleado;
use App\Models\Marcacion;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EmpleadoBorradoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(CatalogoSeeder::class);
    }

    private function actor(string $rol): User
    {
        $u = User::factory()->create();
        $u->assignRole($rol);
        $this->actingAs($u);

        return $u;
    }

    private function empleado(): Empleado
    {
        return Empleado::create(['numero_documento' => '77778888', 'nombres' => 'Mal', 'apellidos' => 'Ingresado']);
    }

    public function test_archivar_es_borrado_logico_y_conserva_el_registro(): void
    {
        $this->actor('RRHH');
        $emp = $this->empleado();

        Volt::test('empleados.tabla')->call('eliminar', $emp->id)->assertHasNoErrors();

        $this->assertNull(Empleado::find($emp->id));                 // oculto de las listas
        $this->assertNotNull(Empleado::withTrashed()->find($emp->id)); // pero sigue en BD
        $this->assertTrue(Empleado::withTrashed()->find($emp->id)->trashed());
    }

    public function test_superadmin_elimina_definitivo_al_mal_ingresado_sin_historial(): void
    {
        $this->actor('SuperAdmin');
        $emp = $this->empleado(); // sin ningún registro asociado

        Volt::test('empleados.tabla')->call('eliminarDefinitivo', $emp->id)->assertHasNoErrors();

        $this->assertNull(Empleado::withTrashed()->find($emp->id)); // ya no existe
    }

    public function test_no_se_puede_eliminar_definitivo_si_tiene_historial(): void
    {
        $this->actor('SuperAdmin');
        $emp = $this->empleado();
        Marcacion::create(['empleado_id' => $emp->id, 'tipo' => 'ingreso', 'fecha_hora' => now()]);

        Volt::test('empleados.tabla')->call('eliminarDefinitivo', $emp->id);

        // Sigue existiendo (se bloqueó)
        $this->assertNotNull(Empleado::withTrashed()->find($emp->id));
    }

    public function test_rrhh_no_puede_eliminar_definitivo(): void
    {
        $this->actor('RRHH');
        $emp = $this->empleado();

        Volt::test('empleados.tabla')->call('eliminarDefinitivo', $emp->id)->assertForbidden();
        $this->assertNotNull(Empleado::withTrashed()->find($emp->id));
    }

    public function test_superadmin_restaura_un_archivado(): void
    {
        $this->actor('SuperAdmin');
        $emp = $this->empleado();
        $emp->delete(); // archivado

        Volt::test('empleados.tabla')->call('restaurar', $emp->id)->assertHasNoErrors();

        $this->assertNotNull(Empleado::find($emp->id)); // vuelve a las listas activas
        $this->assertFalse(Empleado::find($emp->id)->trashed());
    }
}
